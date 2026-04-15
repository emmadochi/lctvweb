import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/video_provider.dart';
import '../../widgets/video_grid_card.dart';
import '../../models/category_model.dart';
import '../../models/video_model.dart';

class ExploreScreen extends StatefulWidget {
  const ExploreScreen({super.key});

  @override
  State<ExploreScreen> createState() => _ExploreScreenState();
}

class _ExploreScreenState extends State<ExploreScreen> {
  int? _selectedCategoryId;
  final TextEditingController _searchController = TextEditingController();
  List<VideoModel> _searchResults = [];
  bool _isSearching = false;
  bool _hasSearched = false;

  Future<void> _handleSearch(String query) async {
    if (query.trim().isEmpty) {
      setState(() {
        _hasSearched = false;
        _searchResults = [];
      });
      return;
    }

    setState(() {
      _isSearching = true;
      _hasSearched = true;
    });

    final provider = context.read<VideoProvider>();
    final results = await provider.searchVideos(query);

    setState(() {
      _searchResults = results;
      _isSearching = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final videoProvider = context.watch<VideoProvider>();
    final categories = videoProvider.categories;

    return Scaffold(
      backgroundColor: const Color(0xFF1A1C3E),
      body: Column(
        children: [
          // --- Search Bar ---
          _buildSearchBar(),

          // --- Categories (Only show when not in active search or if user wants to filter search) ---
          _buildCategories(categories),

          // --- Content Grid ---
          Expanded(
            child: _buildContent(videoProvider),
          ),
        ],
      ),
    );
  }

  Widget _buildSearchBar() {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 16),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(15),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 10, sigmaY: 10),
          child: Container(
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.05),
              border: Border.all(color: Colors.white.withOpacity(0.1)),
              borderRadius: BorderRadius.circular(15),
            ),
            child: TextField(
              controller: _searchController,
              style: const TextStyle(color: Colors.white),
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: 'Search sermons, songs, and more...',
                hintStyle: TextStyle(color: Colors.white.withOpacity(0.3), fontSize: 14),
                prefixIcon: Icon(Icons.search_rounded, color: Colors.white.withOpacity(0.5)),
                suffixIcon: _hasSearched 
                  ? IconButton(
                      icon: const Icon(Icons.clear, color: Colors.white38),
                      onPressed: () {
                        _searchController.clear();
                        setState(() {
                          _hasSearched = false;
                          _searchResults = [];
                        });
                      },
                    )
                  : null,
                border: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(vertical: 15),
              ),
              onSubmitted: _handleSearch,
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildCategories(List<CategoryModel> categories) {
    return Container(
      height: 50,
      margin: const EdgeInsets.only(bottom: 10),
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: categories.length + 1,
        itemBuilder: (context, index) {
          if (index == 0) {
            return _buildCategoryChip(null, 'All');
          }
          final cat = categories[index - 1];
          return _buildCategoryChip(cat.id, cat.name);
        },
      ),
    );
  }

  Widget _buildCategoryChip(int? id, String label) {
    final isSelected = _selectedCategoryId == id;
    return GestureDetector(
      onTap: () {
        setState(() {
          _selectedCategoryId = id;
          if (id != null) {
            _searchController.clear();
            _hasSearched = false;
          }
        });
      },
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        margin: const EdgeInsets.only(right: 12),
        padding: const EdgeInsets.symmetric(horizontal: 20),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFFFB800) : Colors.white.withOpacity(0.05),
          borderRadius: BorderRadius.circular(25),
          border: Border.all(
            color: isSelected ? const Color(0xFFFFB800) : Colors.white.withOpacity(0.1),
          ),
          boxShadow: isSelected ? [
            BoxShadow(
              color: const Color(0xFFFFB800).withOpacity(0.3),
              blurRadius: 10,
              spreadRadius: 1,
            )
          ] : null,
        ),
        child: Text(
          label,
          style: TextStyle(
            color: isSelected ? Colors.black : Colors.white70,
            fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
            fontSize: 13,
          ),
        ),
      ),
    );
  }

  Widget _buildContent(VideoProvider provider) {
    if (_isSearching) {
      return const Center(
        child: CircularProgressIndicator(color: Color(0xFFFFB800)),
      );
    }

    // Determine which list to show
    final List<VideoModel> videos;
    if (_hasSearched) {
      videos = _searchResults;
    } else {
      videos = _selectedCategoryId == null 
          ? provider.recentVideos 
          : provider.recentVideos.where((v) => v.categoryId == _selectedCategoryId).toList();
    }

    if (videos.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.video_library_outlined, size: 60, color: Colors.white.withOpacity(0.2)),
            const SizedBox(height: 16),
            Text(
              _hasSearched ? 'No results found for "${_searchController.text}"' : 'No content found in this category',
              style: TextStyle(color: Colors.white.withOpacity(0.5)),
            ),
            if (_hasSearched) ...[
              const SizedBox(height: 12),
              TextButton(
                onPressed: () {
                   _searchController.clear();
                   setState(() {
                     _hasSearched = false;
                     _searchResults = [];
                   });
                },
                child: const Text('Clear Search', style: TextStyle(color: Color(0xFFFFB800))),
              ),
            ],
          ],
        ),
      );
    }

    return RefreshIndicator(
      color: const Color(0xFFFFB800),
      backgroundColor: const Color(0xFF25284B),
      onRefresh: () async {
        if (_hasSearched) {
          await _handleSearch(_searchController.text);
        } else {
          await provider.loadHomeData();
        }
      },
      child: GridView.builder(
        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          childAspectRatio: 0.75, // Adjusted to fit titles better
          crossAxisSpacing: 14,
          mainAxisSpacing: 16,
        ),
        itemCount: videos.length,
        itemBuilder: (context, index) {
          final video = videos[index];
          return VideoGridCard(video: video);
        },
      ),
    );
  }
}
