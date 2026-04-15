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
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.symmetric(horizontal: 16),
                child: Text(
                  'RECENT VIDEOS',
                  style: TextStyle(
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
            videos.isLoading
                ? _buildSliverShimmerList()
                : videos.recentVideos.isEmpty
                    ? _buildEmptySliver()
                    : SliverList(
                        delegate: SliverChildBuilderDelegate(
                          (context, index) {
                            final video = videos.recentVideos[index];
                            return VideoListCard(video: video);
                          },
                          childCount: videos.recentVideos.length,
                        ),
                      ),

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
    return Stack(
      alignment: Alignment.center,
      children: [
        IconButton(
          icon: const Icon(Icons.notifications_none_rounded, color: Colors.white, size: 26),
          onPressed: () {},
        ),
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

  Widget _buildEmptySliver() {
    return const SliverToBoxAdapter(
      child: Center(
        child: Padding(
          padding: EdgeInsets.all(40),
          child: Text('No videos available', style: TextStyle(color: Colors.white38)),
        ),
      ),
    );
  }
}
