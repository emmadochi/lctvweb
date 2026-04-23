import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shimmer/shimmer.dart';
import '../../providers/auth_provider.dart';
import '../../providers/video_provider.dart';
import '../../providers/livestream_provider.dart';
import '../../widgets/video_list_card.dart';
import '../../widgets/live_banner.dart';

import 'explore_screen.dart';
import 'livestream_screen.dart';
import '../video/my_list_screen.dart';
import '../profile/profile_screen.dart';
import '../leadership/leadership_screen.dart';
import '../giving_screen.dart';
import '../profile/submit_prayer_screen.dart';
import '../profile/notifications_screen.dart';
import '../../providers/notification_provider.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with WidgetsBindingObserver {
  int _selectedIndex = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<VideoProvider>().loadHomeData();
      context.read<LivestreamProvider>().fetchLivestreams();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // If app becomes active, reset PIP state just in case
    if (state == AppLifecycleState.resumed) {
      LivestreamScreen.isPipMode.value = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF1A1C3E),
      body: IndexedStack(
        index: _selectedIndex,
        children: const [
          MainHomeView(),
          ExploreScreen(),
          LivestreamScreen(),
          MyListScreen(),
          ProfileScreen(),
        ],
      ),

      // ── BOTTOM NAVIGATION ──
      bottomNavigationBar: ValueListenableBuilder<bool>(
        valueListenable: LivestreamScreen.isPipMode,
        builder: (context, isPipMode, child) {
          // Robust check: hide if notifier says so OR if the screen size is obviously a PIP window
          final size = MediaQuery.of(context).size;
          final isSmallScreen = size.height < 300; 
          
          if (isPipMode || isSmallScreen) return const SizedBox.shrink();
          return child!;
        },
        child: ClipRect(
          child: BackdropFilter(
            filter: ImageFilter.blur(sigmaX: 15, sigmaY: 15),
            child: Container(
              decoration: BoxDecoration(
                color: const Color(0xFF25284B).withOpacity(0.7),
                border: Border(
                  top: BorderSide(color: Colors.white.withOpacity(0.08), width: 0.5),
                ),
              ),
              child: SafeArea(
                child: SizedBox(
                  height: 65,
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceAround,
                    children: [
                      _buildNavItem(0, Icons.home_filled, 'Home'),
                      _buildNavItem(1, Icons.explore_outlined, 'Explore'),
                      _buildNavItem(2, Icons.sensors, 'Live'),
                      _buildNavItem(3, Icons.list_alt_outlined, 'My List'),
                      _buildNavItem(4, Icons.person_outline, 'Profile'),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem(int index, IconData icon, String label) {
    final isSelected = _selectedIndex == index;
    return GestureDetector(
      onTap: () {
        setState(() => _selectedIndex = index);
      },
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        color: Colors.transparent,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              size: 22,
              color: isSelected ? const Color(0xFFFFB800) : Colors.white38,
            ),
            const SizedBox(height: 3),
            Text(
              label,
              style: TextStyle(
                color: isSelected ? const Color(0xFFFFB800) : Colors.white38,
                fontSize: 10,
                fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showProfileDialog() {
    final authProvider = context.read<AuthProvider>();
    final isGuest = !authProvider.isAuthenticated;

    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        backgroundColor: const Color(0xFF25284B),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Row(
          children: [
            Icon(
              isGuest ? Icons.account_circle_outlined : Icons.verified_user,
              color: const Color(0xFFFFB800),
            ),
            const SizedBox(width: 10),
            Text(
              isGuest ? 'Welcome Guest' : 'Profile',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
            ),
          ],
        ),
        content: Text(
          isGuest 
            ? 'Join LCMTV for a personalized experience, favorites, and more.'
            : 'Logged in as ${authProvider.user?.fullName}\n${authProvider.user?.email}',
          style: const TextStyle(color: Colors.white70),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel', style: TextStyle(color: Colors.white38)),
          ),
          if (isGuest)
            ElevatedButton(
              onPressed: () {
                Navigator.pop(context);
                Navigator.pushNamed(context, '/login');
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFFFB800),
                foregroundColor: Colors.black,
                minimumSize: const Size(100, 40),
              ),
              child: const Text('Login / Sign Up'),
            )
          else
            TextButton(
              onPressed: () {
                Navigator.pop(context);
                authProvider.logout();
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Logged out successfully')),
                );
              },
              child: const Text('Logout', style: TextStyle(color: Colors.redAccent)),
            ),
        ],
      ),
    );
  }
}

class MainHomeView extends StatefulWidget {
  const MainHomeView({super.key});

  @override
  State<MainHomeView> createState() => _MainHomeViewState();
}

class _MainHomeViewState extends State<MainHomeView> {
  int _selectedTab = 0;
  List<String> _tabs = ['HOME', 'WATCH LIVE', 'SERMONS', 'EVENTS', 'MUSIC'];

  @override
  void initState() {
    super.initState();
    _updateTabs();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<NotificationProvider>().fetchUnreadCount();
    });
  }

  void _updateTabs() {
    final auth = context.read<AuthProvider>();
    if (auth.isLeader && !_tabs.contains('LEADERSHIP')) {
      setState(() {
        _tabs.insert(1, 'LEADERSHIP');
      });
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _updateTabs();
  }

  String _getGreeting() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Good Morning';
    if (hour < 17) return 'Good Afternoon';
    return 'Good Evening';
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final videos = context.watch<VideoProvider>();
    final notifications = context.watch<NotificationProvider>();
    final liveProvider = context.watch<LivestreamProvider>();

    final selectedTabName = _tabs[_selectedTab];
    
    // Filter videos based on selection
    List<VideoModel> displayVideos = videos.recentVideos;
    if (selectedTabName == 'WATCH LIVE') {
      displayVideos = [];
    } else if (selectedTabName != 'HOME') {
      final category = videos.categories.firstWhere(
        (c) => c.name.toUpperCase() == selectedTabName || 
               (selectedTabName == 'EVENTS' && c.slug == 'special-events'),
        orElse: () => CategoryModel(id: -1, name: '', slug: ''),
      );
      
      if (category.id != -1) {
        displayVideos = videos.recentVideos.where((v) => v.categoryId == category.id).toList();
      } else {
        // Fallback: search in tags or title if category ID mapping fails
        displayVideos = videos.recentVideos.where((v) => 
          v.title.toUpperCase().contains(selectedTabName) || 
          v.tags.any((t) => t.toUpperCase() == selectedTabName)
        ).toList();
      }
    }

    return SafeArea(
      child: RefreshIndicator(
        color: const Color(0xFFFFB800),
        backgroundColor: const Color(0xFF25284B),
        onRefresh: () async {
          await videos.loadHomeData();
          await context.read<LivestreamProvider>().fetchLivestreams();
        },
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
          slivers: [
            // ── TOP APP BAR ──
            SliverAppBar(
              pinned: true,
              floating: true,
              backgroundColor: const Color(0xFF1A1C3E).withOpacity(0.95),
              elevation: 0,
              titleSpacing: 16,
              title: Row(
                children: [
                  _buildLogo(),
                ],
              ),
              actions: [
                IconButton(
                  icon: const Icon(Icons.volunteer_activism, color: Colors.white, size: 22),
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const GivingScreen()),
                    );
                  },
                ),
                IconButton(
                  icon: const Icon(Icons.front_hand_outlined, color: Colors.white, size: 22),
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const SubmitPrayerScreen()),
                    );
                  },
                ),
                _buildNotificationIcon(),
                const SizedBox(width: 8),
              ],
            ),

            // ── WELCOME HEADER ──
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _getGreeting(),
                            style: const TextStyle(color: Colors.white54, fontSize: 12),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            auth.user != null ? auth.user!.firstName : 'Guest',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 24,
                              fontWeight: FontWeight.w900,
                              letterSpacing: -0.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                    _buildAvatar(auth),
                  ],
                ),
              ),
            ),

            // ── CATEGORY TABS ──
            SliverToBoxAdapter(
              child: SizedBox(
                height: 50,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemCount: _tabs.length,
                  itemBuilder: (context, index) {
                    final isSelected = index == _selectedTab;
                    return _buildTabChip(index, isSelected);
                  },
                ),
              ),
            ),

            const SliverToBoxAdapter(child: SizedBox(height: 20)),

            // ── LIVE BANNER ──
            SliverToBoxAdapter(
              child: Builder(builder: (context) {
                final liveProvider = context.watch<LivestreamProvider>();
                if (videos.isLoading || liveProvider.isLoading) {
                  return _buildShimmerBanner();
                }
                if (liveProvider.activeStreams.isEmpty) {
                  return const SizedBox.shrink();
                }
                return LiveBanner(streams: liveProvider.activeStreams);
              }),
            ),

            const SliverToBoxAdapter(child: SizedBox(height: 28)),

            // ── RECENT VIDEOS HEADER ──
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Text(
                  selectedTabName == 'HOME' ? 'RECENT VIDEOS' : selectedTabName,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1.5,
                  ),
                ),
              ),
            ),

            const SliverToBoxAdapter(child: SizedBox(height: 12)),

            // ── VIDEO LIST ──
            _buildVideoList(videos, displayVideos, selectedTabName),

            const SliverToBoxAdapter(child: SizedBox(height: 100)),
          ],
        ),
      ),
    );
  }

  Widget _buildLogo() {
    return Image.asset(
      'assets/images/logo.png',
      height: 32,
      fit: BoxFit.contain,
      errorBuilder: (context, error, stackTrace) => Container(
        width: 32,
        height: 32,
        color: Colors.white24,
        child: const Icon(Icons.broken_image, size: 16),
      ),
    );
  }

  Widget _buildNotificationIcon() {
    final notifications = context.watch<NotificationProvider>();
    final hasUnread = notifications.unreadCount > 0;

    return Stack(
      alignment: Alignment.center,
      children: [
        IconButton(
          icon: const Icon(Icons.notifications_none_rounded, color: Colors.white, size: 26),
          onPressed: () {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const NotificationsScreen()),
            );
          },
        ),
        if (hasUnread)
          Positioned(
            top: 12,
            right: 12,
            child: Container(
              width: 8,
              height: 8,
              decoration: const BoxDecoration(
                color: Color(0xFFFFB800),
                shape: BoxShape.circle,
              ),
            ),
          ),
      ],
    );
  }

  Widget _buildAvatar(AuthProvider auth) {
    return Container(
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: const Color(0xFFFFB800).withOpacity(0.5), width: 1),
      ),
      child: CircleAvatar(
        radius: 20,
        backgroundColor: const Color(0xFF25284B),
        child: Text(
          auth.user != null ? auth.user!.firstName[0] : 'G',
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
        ),
      ),
    );
  }

  Widget _buildTabChip(int index, bool isSelected) {
    return GestureDetector(
      onTap: () {
        if (_tabs[index] == 'LEADERSHIP') {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const LeadershipScreen()),
          );
          return;
        }
        setState(() => _selectedTab = index);
      },
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 300),
        margin: const EdgeInsets.only(right: 12),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFFFB800) : const Color(0xFF25284B).withOpacity(0.5),
          borderRadius: BorderRadius.circular(25),
          border: Border.all(
            color: isSelected ? const Color(0xFFFFB800) : Colors.white.withOpacity(0.05),
          ),
        ),
        child: Center(
          child: Text(
            _tabs[index],
            style: TextStyle(
              color: isSelected ? Colors.black : Colors.white54,
              fontSize: 11,
              fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildShimmerBanner() {
    return Shimmer.fromColors(
      baseColor: const Color(0xFF25284B),
      highlightColor: const Color(0xFF3A3E6B),
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 16),
        height: 180,
        decoration: BoxDecoration(
          color: const Color(0xFF25284B),
          borderRadius: BorderRadius.circular(16),
        ),
      ),
    );
  }

  Widget _buildSliverShimmerList() {
    return SliverList(
      delegate: SliverChildBuilderDelegate(
        (_, __) => Shimmer.fromColors(
          baseColor: const Color(0xFF25284B),
          highlightColor: const Color(0xFF3A3E6B),
          child: Container(
            margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
            height: 96,
            decoration: BoxDecoration(
              color: const Color(0xFF25284B),
              borderRadius: BorderRadius.circular(14),
            ),
          ),
        ),
        childCount: 5,
      ),
    );
  }

  Widget _buildVideoList(VideoProvider provider, List<VideoModel> videos, String selectedTab) {
    if (provider.isLoading) return _buildSliverShimmerList();
    
    if (selectedTab == 'WATCH LIVE') {
      final liveProvider = context.read<LivestreamProvider>();
      if (liveProvider.activeStreams.isEmpty) {
        return _buildEmptySliver(message: 'No live streams currently active');
      }
      // If live streams exist, they are already shown in the LiveBanner above.
      // We can just show a small note or nothing here.
      return const SliverToBoxAdapter(child: SizedBox.shrink());
    }

    if (videos.isEmpty) {
      return _buildEmptySliver(message: 'No content found in $selectedTab');
    }

    return SliverList(
      delegate: SliverChildBuilderDelegate(
        (context, index) {
          final video = videos[index];
          return VideoListCard(video: video);
        },
        childCount: videos.length,
      ),
    );
  }

  Widget _buildEmptySliver({String message = 'No videos available'}) {
    return SliverToBoxAdapter(
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(60),
          child: Column(
            children: [
              Icon(Icons.video_library_outlined, size: 48, color: Colors.white.withOpacity(0.1)),
              const SizedBox(height: 16),
              Text(message, style: const TextStyle(color: Colors.white38)),
            ],
          ),
        ),
      ),
    );
  }
}
