import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:video_player/video_player.dart';
import 'package:chewie/chewie.dart';
import 'package:youtube_player_flutter/youtube_player_flutter.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';
import '../../models/video_model.dart';
import '../../providers/video_provider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/history_provider.dart';
import '../../providers/donation_provider.dart';
import 'package:flutter_paystack/flutter_paystack.dart';
import '../../widgets/video_list_card.dart';
import '../../widgets/video_grid_card.dart';
import '../home/livestream_screen.dart';
import '../giving_screen.dart';
import 'package:simple_pip_mode/simple_pip.dart';
import 'package:simple_pip_mode/pip_widget.dart';
import 'package:simple_pip_mode/actions/pip_action.dart';
import 'package:simple_pip_mode/actions/pip_actions_layout.dart';
import '../../main.dart';
import 'package:audio_service/audio_service.dart';
import 'package:audio_session/audio_session.dart';


class VideoPlayerScreen extends StatefulWidget {
  final VideoModel video;

  const VideoPlayerScreen({super.key, required this.video});

  @override
  State<VideoPlayerScreen> createState() => _VideoPlayerScreenState();
}

class _VideoPlayerScreenState extends State<VideoPlayerScreen> with WidgetsBindingObserver {
  // Hybrid Player State
  VideoPlayerController? _videoController;
  ChewieController? _chewieController;
  YoutubePlayerController? _youtubeController;
  
  bool _isPlayerInitialized = false;
  bool _hasError = false;
  bool _showPaywall = false;
  bool _isPurchasing = false;
  final _paystackPlugin = PaystackPlugin();
  bool _isPaystackInitialized = false;
  List<VideoModel> _relatedVideos = [];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    
    _checkAccessAndInit();
    _loadRelated();
    
    // Configure auto PIP on start
    SimplePip().setAutoPipMode(aspectRatio: const (16, 9));
    
    // Add to watch history
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        context.read<HistoryProvider>().addToHistory(widget.video.id);
      }
    });

    SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle.light);
  }

  void _onPipEntered() {
    LivestreamScreen.isPipMode.value = true;
    _resumeIfInPip();
    
    // Reinforced playback reinforcement
    Future.delayed(const Duration(milliseconds: 500), () {
      if (LivestreamScreen.isPipMode.value) {
        _resumeIfInPip();
      }
    });
  }

  void _onPipExited() {
    LivestreamScreen.isPipMode.value = false;
  }

  void _handlePipAction(PipAction action) {
    if (action == PipAction.play) {
      _resumeIfInPip();
    } else if (action == PipAction.pause) {
      _youtubeController?.pause();
      _videoController?.pause();
    }
  }

  void _resumeIfInPip() {
    if (_youtubeController != null) {
      _youtubeController?.play();
    } else {
      _videoController?.play();
    }
    SimplePip().setIsPlaying(true);
  }


  void _checkAccessAndInit() {
    if (widget.video.isPremium && !widget.video.hasAccess) {
      setState(() => _showPaywall = true);
    } else {
      _initHybridPlayer();
    }
  }

  Future<void> _handlePurchase() async {
    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please login to purchase this video')),
      );
      return;
    }

    setState(() => _isPurchasing = true);

    try {
      final videoProvider = context.read<VideoProvider>();
      final purchaseData = await videoProvider.initiatePurchase(widget.video.id, currency: 'NGN');

      if (purchaseData == null) {
        throw videoProvider.error ?? 'Failed to initiate purchase. Please check your connection.';
      }

      final String reference = purchaseData['reference'];
      
      // Handle case where video is already purchased
      if (reference == 'ALREADY_PURCHASED' || purchaseData['already_purchased'] == true) {
        if (mounted) {
          setState(() {
            _showPaywall = false;
            _isPurchasing = false;
          });
          _initHybridPlayer();
        }
        return;
      }

      final donationProvider = context.read<DonationProvider>();
      if (donationProvider.paymentSettings.isEmpty) {
        await donationProvider.loadPaymentSettings();
      }
      
      final publicKey = donationProvider.paymentSettings['gateway']?.firstWhere(
        (s) => s['setting_key'].toString().toLowerCase().contains('paystack_public'),
        orElse: () => null,
      )?['setting_value'];

      if (publicKey == null) {
        throw 'Payment gateway is not currently configured.';
      }

      if (!_isPaystackInitialized) {
        await _paystackPlugin.initialize(publicKey: publicKey);
        _isPaystackInitialized = true;
      }

      final int amountInKobo = (widget.video.price * 100).toInt();

      Charge charge = Charge()
        ..amount = amountInKobo
        ..reference = reference
        ..email = auth.user?.email ?? ''
        ..currency = 'NGN';

      final response = await _paystackPlugin.checkout(
        context,
        charge: charge,
        method: CheckoutMethod.card,
        logo: Image.asset('assets/images/logo.png', width: 40),
      );

      if (response.status) {
        final verified = await videoProvider.verifyPurchase(reference);
        if (verified && mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Purchase successful!')),
          );
          setState(() {
            _showPaywall = false;
            _isPurchasing = false;
          });
          _initHybridPlayer();
        } else {
          throw 'Payment verification failed';
        }
      } else {
        setState(() => _isPurchasing = false);
      }
    } catch (e) {
      debugPrint('Purchase error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
        setState(() => _isPurchasing = false);
      }
    }
  }

  Future<void> _requestAudioFocus() async {
    final session = await AudioSession.instance;
    await session.setActive(true);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused || state == AppLifecycleState.inactive) {
      if (LivestreamScreen.isPipMode.value) {
        _resumeIfInPip();
      }
      // Note: We don't pause the player here anymore, 
      // allowing background audio to continue.
    }
  }


  Future<void> _initHybridPlayer() async {
    try {
      if (widget.video.youtubeId != null && widget.video.youtubeId!.isNotEmpty) {
        _youtubeController = YoutubePlayerController(
          initialVideoId: widget.video.youtubeId!,
          flags: const YoutubePlayerFlags(
            autoPlay: true,
            mute: false,
            disableDragSeek: false,
            loop: false,
            isLive: false,
            forceHD: true,
            enableCaption: true,
          ),
        );
        
        _setupYoutubeAudioHandler();
        
        setState(() => _isPlayerInitialized = true);
      } else {
        final url = widget.video.videoUrl;
        if (url.isEmpty) {
          return;
        }

        _videoController = VideoPlayerController.networkUrl(
          Uri.parse(url),
          videoPlayerOptions: VideoPlayerOptions(mixWithOthers: true),
        );
        await _videoController!.initialize();

        // Request audio focus before starting background playback
        await _requestAudioFocus();

        // Initialize Audio Service Handler for this video
        _setupAudioHandler();

        _chewieController = ChewieController(

          videoPlayerController: _videoController!,
          autoPlay: true,
          looping: false,
          allowFullScreen: true,
          showControls: true,
          materialProgressColors: ChewieProgressColors(
            playedColor: const Color(0xFFFFB800),
            handleColor: const Color(0xFFFFB800),
            backgroundColor: Colors.white12,
            bufferedColor: Colors.white24,
          ),
          placeholder: _buildThumbnailPlaceholder(),
        );
        setState(() => _isPlayerInitialized = true);
      }
    } catch (e) {
      print('Video player initialization error: $e');
      if (mounted) setState(() => _hasError = true);
    }
  }

  void _setupAudioHandler() {
    if (_videoController == null) return;

    // 1. Update Metadata
    audioHandler.updateMetadata(
      id: widget.video.id.toString(),

      title: widget.video.title,
      artist: widget.video.channelTitle,
      artUri: widget.video.thumbnailUrl,
      duration: _videoController!.value.duration,
    );

    // 2. Set Callbacks for lock screen controls
    audioHandler.onPlayCallback = () async => await _videoController?.play();
    audioHandler.onPauseCallback = () async => await _videoController?.pause();
    audioHandler.onSeekCallback = (position) async => await _videoController?.seekTo(position);
    audioHandler.onStopCallback = () async => await _videoController?.pause();


    // 3. Listen for changes in the player to update notification state
    _videoController!.addListener(() {
      if (!mounted) return;
      
      final value = _videoController!.value;
      audioHandler.updatePlaybackState(
        playing: value.isPlaying,
        processingState: value.isBuffering 
          ? AudioProcessingState.buffering 
          : (value.isInitialized ? AudioProcessingState.ready : AudioProcessingState.idle),
        position: value.position,
        bufferedPosition: value.buffered.isNotEmpty ? value.buffered.last.end : Duration.zero,
      );

    });
  }

  void _setupYoutubeAudioHandler() {
    // Basic handler for YouTube to show metadata on lock screen
    audioHandler.updateMetadata(
      id: widget.video.id.toString(),
      title: widget.video.title,
      artist: widget.video.channelTitle,
      artUri: widget.video.thumbnailUrl,
    );

    audioHandler.onPlayCallback = () async => _youtubeController?.play();
    audioHandler.onPauseCallback = () async => _youtubeController?.pause();
    
    _youtubeController!.addListener(() {
      if (!mounted) return;
      audioHandler.updatePlaybackState(
        playing: _youtubeController!.value.isPlaying,
        processingState: _youtubeController!.value.isReady 
          ? AudioProcessingState.ready 
          : AudioProcessingState.buffering,
      );
    });
  }


  Future<void> _loadRelated() async {
    final provider = context.read<VideoProvider>();

    // Tier 1: Try searching by channel title for relevant content
    if (widget.video.channelTitle != null && widget.video.channelTitle!.isNotEmpty) {
      try {
        final results = await provider.searchVideos(widget.video.channelTitle!);
        final filtered = results.where((v) => v.id != widget.video.id).take(10).toList();
        if (filtered.isNotEmpty && mounted) {
          setState(() => _relatedVideos = filtered);
          return;
        }
      } catch (_) {}
    }

    // Tier 2: Fallback to already-loaded recent/featured videos from cache
    final cached = [
      ...provider.recentVideos,
      ...provider.featuredVideos,
    ].where((v) => v.id != widget.video.id).toSet().take(10).toList();

    if (cached.isNotEmpty && mounted) {
      setState(() => _relatedVideos = cached);
      return;
    }

    // Tier 3: Fetch latest videos from API directly
    try {
      final results = await provider.searchVideos('');
      final filtered = results.where((v) => v.id != widget.video.id).take(10).toList();
      if (mounted) setState(() => _relatedVideos = filtered);
    } catch (_) {}
  }

  void _shareVideo() {
    final String shareLink = 'https://tv.lifechangerstouch.org/video/${widget.video.id}';
    Share.share(
      'Watch "${widget.video.title}" on LCMTV: $shareLink',
      subject: 'Check out this video on LCMTV',
    );
  }

  Widget _buildThumbnailPlaceholder() {
    return CachedNetworkImage(
      imageUrl: widget.video.thumbnailUrl,
      fit: BoxFit.cover,
      errorWidget: (_, __, ___) => Container(
        color: const Color(0xFF25284B),
        child: const Center(
          child: Icon(Icons.play_circle_outline, color: Colors.white24, size: 60),
        ),
      ),
    );
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _videoController?.dispose();
    _chewieController?.dispose();
    _youtubeController?.dispose();
    SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
    super.dispose();
  }

  String _formatViews(int views) {
    if (views >= 1000000) return '${(views / 1000000).toStringAsFixed(1)}M views';
    if (views >= 1000) return '${(views / 1000).toStringAsFixed(1)}K views';
    return '$views views';
  }

  String _formatDate(DateTime? date) {
    if (date == null) return '';
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }

  @override
  Widget build(BuildContext context) {
    final videoProvider = context.watch<VideoProvider>();
    final isFavorited = videoProvider.isFavorite(widget.video.id);

    return PipWidget(
      onPipEntered: _onPipEntered,
      onPipExited: _onPipExited,
      onPipAction: _handlePipAction,
      pipLayout: PipActionsLayout.media,
      pipChild: _buildPlayer(isPip: true),
      child: Scaffold(
        backgroundColor: const Color(0xFF1A1C3E),
        body: Column(
          children: [
            Container(
              color: Colors.black,
              child: SafeArea(
                bottom: false,
                top: !LivestreamScreen.isPipMode.value,
                child: AspectRatio(
                  aspectRatio: _showPaywall ? 1.0 : 16 / 9,
                  child: _buildPlayer(),
                ),
              ),
            ),

            Expanded(
              child: CustomScrollView(
                slivers: [
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(8, 12, 16, 0),
                      child: Row(
                        children: [
                          IconButton(
                            icon: const Icon(Icons.arrow_back_ios_new,
                                color: Colors.white, size: 18),
                            onPressed: () => Navigator.pop(context),
                          ),
                          const Spacer(),
                          IconButton(
                            icon: const Icon(Icons.share_outlined,
                                color: Colors.white54, size: 20),
                            onPressed: _shareVideo,
                          ),
                          IconButton(
                            icon: Icon(
                              isFavorited ? Icons.favorite : Icons.favorite_border,
                              color: isFavorited ? const Color(0xFFFFB800) : Colors.white54,
                              size: 20,
                            ),
                            onPressed: () => videoProvider.toggleFavorite(widget.video),
                          ),
                          IconButton(
                            icon: const Icon(Icons.volunteer_activism, color: Color(0xFFFFB800), size: 18),
                            tooltip: 'Give Online',
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute(builder: (_) => const GivingScreen()),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          widget.video.title.toUpperCase(),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                            height: 1.3,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            if (widget.video.channelTitle != null)
                              Text(
                                widget.video.channelTitle!,
                                style: const TextStyle(
                                  color: Colors.white60,
                                  fontSize: 13,
                                ),
                              ),
                            const Spacer(),
                            Text(
                              '${_formatDate(widget.video.publishedAt)}  •  ${_formatViews(widget.video.viewCount)}',
                              style: const TextStyle(
                                  color: Colors.white38, fontSize: 12),
                            ),
                          ],
                        ),
                        if (widget.video.description != null &&
                            widget.video.description!.isNotEmpty) ...[
                          const SizedBox(height: 12),
                          Text(
                            widget.video.description!,
                            style: const TextStyle(
                                color: Colors.white54, fontSize: 13, height: 1.5),
                            maxLines: 3,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                        const SizedBox(height: 16),
                        Container(height: 1, color: Colors.white10),
                      ],
                    ),
                  ),
                ),

                if (_relatedVideos.isNotEmpty) ...[
                  const SliverToBoxAdapter(
                    child: Padding(
                      padding: EdgeInsets.fromLTRB(16, 20, 16, 12),
                      child: Text(
                        'RELATED VIDEOS',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 1.5,
                        ),
                      ),
                    ),
                  ),
                  SliverToBoxAdapter(
                    child: SizedBox(
                      height: 230,
                      child: ListView.builder(
                        scrollDirection: Axis.horizontal,
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        itemCount: _relatedVideos.length,
                        itemBuilder: (context, index) {
                          return Container(
                            width: 260,
                            margin: const EdgeInsets.only(right: 16),
                            child: VideoGridCard(video: _relatedVideos[index]),
                          );
                        },
                      ),
                    ),
                  ),
                ],

                const SliverToBoxAdapter(child: SizedBox(height: 30)),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

  Widget _buildPlayer({bool isPip = false}) {
    return Stack(
      alignment: Alignment.center,
      children: [
        // 1. Paywall Overlay (Highest Priority)
        if (_showPaywall)
          Positioned.fill(
            child: Container(
              color: Colors.black87,
              child: Center(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(20.0),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.lock_outline, color: Color(0xFFFFB800), size: 40),
                      const SizedBox(height: 12),
                      const Text(
                        'Premium Content',
                        style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 4),
                      const Text(
                        'This video requires a purchase to watch',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Colors.white70, fontSize: 13),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'NGN ${widget.video.price.toStringAsFixed(2)}',
                        style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 20),
                      SizedBox(
                        width: double.infinity,
                        height: 50,
                        child: ElevatedButton(
                          onPressed: _isPurchasing ? null : _handlePurchase,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFFFFB800),
                            foregroundColor: Colors.black,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            elevation: 0,
                          ),
                          child: _isPurchasing
                              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black))
                               : const Text('PURCHASE NOW', style: TextStyle(fontWeight: FontWeight.bold, letterSpacing: 1.1)),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),

        // 2. Main Player Area
        if (!_showPaywall) ...[
          if (_isPlayerInitialized) ...[
            if (_youtubeController != null)
              YoutubePlayer(
                controller: _youtubeController!,
                showVideoProgressIndicator: true,
                progressIndicatorColor: const Color(0xFFFFB800),
                progressColors: const ProgressBarColors(
                  playedColor: Color(0xFFFFB800),
                  handleColor: Color(0xFFFFB800),
                ),
              )
            else if (_chewieController != null)
              Chewie(controller: _chewieController!)
            else
              const SizedBox.shrink(),
          ] else ...[
            // Loading/Error placeholders
            _buildThumbnailPlaceholder(),
            if (_hasError)
              const Center(
                child: Text('Failed to load video',
                    style: TextStyle(color: Colors.redAccent)),
              )
            else
              const Center(
                child: CircularProgressIndicator(
                  color: Color(0xFFFFB800),
                  strokeWidth: 2,
                ),
              ),
          ],
        ],
      ],
    );
  }
}
