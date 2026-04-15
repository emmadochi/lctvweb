import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:youtube_player_flutter/youtube_player_flutter.dart';
import 'package:video_player/video_player.dart';
import 'package:simple_pip_mode/simple_pip.dart';
import 'package:simple_pip_mode/pip_widget.dart';
import 'package:simple_pip_mode/actions/pip_action.dart';
import 'package:simple_pip_mode/actions/pip_actions_layout.dart';
import '../../providers/livestream_provider.dart';
import '../../widgets/chat_widget.dart';

class LivestreamScreen extends StatefulWidget {
  const LivestreamScreen({super.key});

  static final ValueNotifier<bool> isPipMode = ValueNotifier(false);

  @override
  State<LivestreamScreen> createState() => _LivestreamScreenState();
}

class _LivestreamScreenState extends State<LivestreamScreen> with WidgetsBindingObserver {
  // Hybrid Player State
  YoutubePlayerController? _youtubeController;
  VideoPlayerController? _hlsController;
  
  bool _isPlayerInitialized = false;
  bool _hasError = false;
  bool _isChatExpanded = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    
    // Configure auto PIP on start
    SimplePip().setAutoPipMode(aspectRatio: const (16, 9));
    
    // Fetch livestreams and initialize the first one found
    _fetchAndInit();
  }

  Future<void> _fetchAndInit() async {
    final provider = context.read<LivestreamProvider>();
    await provider.fetchLivestreams();
    if (provider.featuredStream != null) {
      _initHybridPlayer(provider.featuredStream!);
    } else {
      setState(() => _hasError = true);
    }
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
      _hlsController?.pause();
      SimplePip().setIsPlaying(false);
    }
  }

  void _resumeIfInPip() {
    if (_youtubeController != null) {
      _youtubeController?.play();
    } else {
      _hlsController?.play();
    }
    SimplePip().setIsPlaying(true);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused || state == AppLifecycleState.inactive) {
      if (LivestreamScreen.isPipMode.value) {
        _resumeIfInPip();
      }
    }
  }

  Future<void> _initHybridPlayer(dynamic stream) async {
    // Stop heartbeat for previous stream if any
    context.read<LivestreamProvider>().stopHeartbeat();
    context.read<LivestreamProvider>().startHeartbeat(stream.id);

    try {
      // Prefer HLS if available
      if (stream.hlsUrl != null && stream.hlsUrl!.isNotEmpty) {
        _hlsController = VideoPlayerController.networkUrl(Uri.parse(stream.hlsUrl!));
        await _hlsController!.initialize();
        _hlsController!.play();
        setState(() => _isPlayerInitialized = true);
        SimplePip().setIsPlaying(true);
      } else {
        _youtubeController = YoutubePlayerController(
          initialVideoId: stream.youtubeId,
          flags: const YoutubePlayerFlags(
            autoPlay: true,
            mute: false,
            isLive: true,
            forceHD: false,
            enableCaption: true,
          ),
        );
        setState(() => _isPlayerInitialized = true);
        SimplePip().setIsPlaying(true);
      }
    } catch (e) {
      print('Livestream player initialization error: $e');
      if (mounted) setState(() => _hasError = true);
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    context.read<LivestreamProvider>().stopHeartbeat();
    _youtubeController?.dispose();
    _hlsController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<LivestreamProvider>();
    final stream = provider.featuredStream;

    return PipWidget(
      onPipEntered: _onPipEntered,
      onPipExited: _onPipExited,
      onPipAction: _handlePipAction,
      pipLayout: PipActionsLayout.media,
      pipChild: _buildPlayer(stream, isPip: true),
      child: Scaffold(
        backgroundColor: const Color(0xFF1A1C3E),
        body: Column(
          children: [
            // Safe Player Area (Responsive to PIP)
            Container(
              color: Colors.black,
              child: SafeArea(
                bottom: false,
                top: !LivestreamScreen.isPipMode.value,
                child: AspectRatio(
                  aspectRatio: 16 / 9,
                  child: _buildPlayer(stream),
                ),
              ),
            ),

            // Main Content Area
            Expanded(
              child: Stack(
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Stream Info Bar
                      if (stream != null) _buildStreamMeta(stream),
                      
                      // Chat Zone
                      if (!_isChatExpanded && stream != null)
                        Expanded(
                          child: ChatWidget(
                            videoId: stream.id,
                            isLivestream: true,
                            isExpanded: false,
                            onExpandToggle: () => setState(() => _isChatExpanded = true),
                          ),
                        ),
                      
                      if (_hasError) _buildEmptyState(),
                      if (provider.isLoading && stream == null)
                        const Expanded(child: Center(child: CircularProgressIndicator(color: Color(0xFFFFB800)))),
                    ],
                  ),

                  // Full-screen Chat Overlay
                  if (_isChatExpanded && stream != null)
                    Positioned.fill(
                      child: Container(
                        color: Colors.black.withOpacity(0.5),
                        child: ChatWidget(
                          videoId: stream.id,
                          isLivestream: true,
                          isExpanded: true,
                          onExpandToggle: () => setState(() => _isChatExpanded = false),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStreamMeta(dynamic stream) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [Colors.black.withOpacity(0.2), Colors.transparent],
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _buildLiveBadge(),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  stream.title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              Icon(Icons.person_outline, size: 14, color: Colors.white.withOpacity(0.5)),
              const SizedBox(width: 4),
              Text(
                '${stream.viewerCount} watching now',
                style: TextStyle(
                  color: Colors.white.withOpacity(0.5),
                  fontSize: 12,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Container(height: 1, color: Colors.white10),
        ],
      ),
    );
  }

  Widget _buildPlayer(dynamic stream, {bool isPip = false}) {
    if (!_isPlayerInitialized || stream == null) {
      return Stack(
        fit: StackFit.expand,
        children: [
          if (stream != null) _buildThumbnail(stream),
          if (!_hasError)
            const Center(
              child: CircularProgressIndicator(
                color: Color(0xFFFFB800),
                strokeWidth: 2,
              ),
            ),
        ],
      );
    }

    if (_youtubeController != null) {
      return YoutubePlayer(
        controller: _youtubeController!,
        showVideoProgressIndicator: true,
        progressIndicatorColor: const Color(0xFFFFB800),
      );
    }

    if (_hlsController != null) {
      return Center(
        child: AspectRatio(
          aspectRatio: _hlsController!.value.aspectRatio,
          child: VideoPlayer(_hlsController!),
        ),
      );
    }

    return const SizedBox.shrink();
  }

  Widget _buildThumbnail(dynamic stream) {
    final thumbUrl = stream.thumbnailUrl.isNotEmpty 
        ? stream.thumbnailUrl 
        : 'https://img.youtube.com/vi/${stream.youtubeId}/hqdefault.jpg';

    return Image.network(
      thumbUrl, 
      fit: BoxFit.cover,
      errorBuilder: (context, error, stackTrace) => 
        Container(color: const Color(0xFF1A1C3E), child: const Center(child: Icon(Icons.live_tv, size: 64, color: Colors.white24))),
    );
  }

  Widget _buildLiveBadge() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.red,
        borderRadius: BorderRadius.circular(4),
      ),
      child: const Text(
        'LIVE',
        style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
      ),
    );
  }

  Widget _buildEmptyState() {
    return Expanded(
      child: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.sensors_off, size: 80, color: Colors.white.withOpacity(0.1)),
            const SizedBox(height: 20),
            const Text(
              'No active livestreams at the moment.',
              style: TextStyle(color: Colors.white54, fontSize: 16),
            ),
          ],
        ),
      ),
    );
  }
}
