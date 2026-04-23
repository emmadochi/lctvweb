import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../../providers/prayer_provider.dart';
import '../../models/prayer_request_model.dart';
import 'submit_prayer_screen.dart';

class PrayerRequestsScreen extends StatefulWidget {
  const PrayerRequestsScreen({super.key});

  @override
  State<PrayerRequestsScreen> createState() => _PrayerRequestsScreenState();
}

class _PrayerRequestsScreenState extends State<PrayerRequestsScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => context.read<PrayerProvider>().fetchUserRequests());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF1A1C3E),
      appBar: AppBar(
        title: const Text('My Prayer Requests', style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.transparent,
        elevation: 0,
      ),
      body: Consumer<PrayerProvider>(
        builder: (context, provider, child) {
          if (provider.isLoading && provider.requests.isEmpty) {
            return const Center(child: CircularProgressIndicator(color: Color(0xFFFFB800)));
          }

          if (provider.requests.isEmpty) {
            return _buildEmptyState();
          }

          return RefreshIndicator(
            onRefresh: () => provider.fetchUserRequests(),
            color: const Color(0xFFFFB800),
            backgroundColor: const Color(0xFF25284B),
            child: ListView.builder(
              padding: const EdgeInsets.all(20),
              itemCount: provider.requests.length,
              itemBuilder: (context, index) {
                final request = provider.requests[index];
                return _buildRequestCard(request);
              },
            ),
          );
        },
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const SubmitPrayerScreen()),
          );
        },
        backgroundColor: const Color(0xFFFFB800),
        child: const Icon(Icons.add, color: Colors.black),
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(30),
            decoration: BoxDecoration(
              color: const Color(0xFF25284B),
              shape: BoxShape.circle,
            ),
            child: Icon(Icons.front_hand_outlined, size: 80, color: Colors.white.withOpacity(0.05)),
          ),
          const SizedBox(height: 25),
          const Text(
            'No prayer requests yet',
            style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 10),
          const Text(
            'Tap the + button to submit your first request.',
            style: TextStyle(color: Colors.white54, fontSize: 14),
          ),
          const SizedBox(height: 40),
          ElevatedButton(
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const SubmitPrayerScreen()),
              );
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFFFB800),
              foregroundColor: Colors.black,
              padding: const EdgeInsets.symmetric(horizontal: 30, vertical: 15),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: const Text('SUBMIT REQUEST', style: TextStyle(fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  Widget _buildRequestCard(PrayerRequestModel request) {
    final bool isAnswered = request.status.toLowerCase() == 'answered';
    final dateStr = DateFormat('MMM dd, yyyy • hh:mm a').format(request.createdAt);

    return Container(
      margin: const EdgeInsets.only(bottom: 20),
      decoration: BoxDecoration(
        color: const Color(0xFF25284B),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white.withOpacity(0.05)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.1),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: isAnswered 
                        ? Colors.green.withOpacity(0.15) 
                        : const Color(0xFFFFB800).withOpacity(0.15),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: isAnswered ? Colors.green.withOpacity(0.3) : const Color(0xFFFFB800).withOpacity(0.3),
                    ),
                  ),
                  child: Text(
                    request.status.toUpperCase(),
                    style: TextStyle(
                      color: isAnswered ? Colors.greenAccent : const Color(0xFFFFB800),
                      fontSize: 10,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 1,
                    ),
                  ),
                ),
                const Spacer(),
                Text(
                  dateStr,
                  style: const TextStyle(color: Colors.white38, fontSize: 11),
                ),
              ],
            ),
          ),
          
          // Request Body
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.05),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Text(
                    request.category,
                    style: const TextStyle(color: Color(0xFFFFB800), fontSize: 11, fontWeight: FontWeight.bold),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  request.requestText,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 15,
                    height: 1.5,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 20),

          // Admin Response
          if (request.adminResponse != null)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.black.withOpacity(0.2),
                borderRadius: const BorderRadius.vertical(bottom: Radius.circular(16)),
                border: Border(
                  top: BorderSide(color: Colors.white.withOpacity(0.05)),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.reply_rounded, color: Color(0xFFFFB800), size: 16),
                      const SizedBox(width: 8),
                      const Text(
                        'Ministry Response',
                        style: TextStyle(
                          color: Color(0xFFFFB800),
                          fontWeight: FontWeight.bold,
                          fontSize: 13,
                        ),
                      ),
                      if (request.respondedAt != null) ...[
                        const Spacer(),
                        Text(
                          DateFormat('MMM dd').format(request.respondedAt!),
                          style: const TextStyle(color: Colors.white30, fontSize: 10),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 10),
                  Text(
                    request.adminResponse!,
                    style: const TextStyle(
                      color: Colors.white70,
                      fontSize: 14,
                      fontStyle: FontStyle.italic,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerRight,
                    child: Text(
                      '— ${request.responderName}',
                      style: const TextStyle(color: Colors.white38, fontSize: 11),
                    ),
                  ),
                ],
              ),
            )
          else
            const SizedBox(height: 16),
        ],
      ),
    );
  }
}
