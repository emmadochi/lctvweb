import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';

class PrivacyPolicyScreen extends StatelessWidget {
  const PrivacyPolicyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(title: const Text('Privacy Policy')),
      body: const SingleChildScrollView(
        padding: EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Privacy Policy for LCMTV',
              style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold),
            ),
            SizedBox(height: 10),
            Text(
              'Last updated: April 13, 2026',
              style: TextStyle(color: AppTheme.accentColor, fontSize: 12),
            ),
            SizedBox(height: 30),
            _PolicySection(
              title: '1. Information We Collect',
              content: 'We collect information you provide directly to us when you create an account, such as your name, email address, and profile details. We also collect data about your interactions with our content, including watch history.',
            ),
            _PolicySection(
              title: '2. How We Use Your Information',
              content: 'We use the information we collect to provide, maintain, and improve our services, including personalizing your experience, providing watch history, and sending notifications about new content.',
            ),
            _PolicySection(
              title: '3. Data Sharing',
              content: 'We do not sell your personal data. We may share information with service providers who perform services for us, or when required by law.',
            ),
            _PolicySection(
              title: '4. Your Choices',
              content: 'You can update your profile information and change your password within the app settings. You can also clear your watch history at any time.',
            ),
            _PolicySection(
              title: '5. Contact Us',
              content: 'If you have any questions about this Privacy Policy, please contact us at support@lifechangerstouch.org.',
            ),
          ],
        ),
      ),
    );
  }
}

class _PolicySection extends StatelessWidget {
  final String title;
  final String content;

  const _PolicySection({required this.title, required this.content});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 10),
          Text(
            content,
            style: const TextStyle(color: Colors.white70, fontSize: 14, height: 1.6),
          ),
        ],
      ),
    );
  }
}
