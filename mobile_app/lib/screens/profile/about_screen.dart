import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';

class AboutScreen extends StatelessWidget {
  const AboutScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(title: const Text('About LCMTV')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          children: [
            const SizedBox(height: 20),
            Center(child: Image.asset('assets/images/logo.png', width: 120)),
            const SizedBox(height: 30),
            const Text(
              'LCMTV (Life Changers TV)',
              style: TextStyle(
                color: Colors.white,
                fontSize: 24,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 10),
            const Text(
              'Version 1.0.0',
              style: TextStyle(color: AppTheme.accentColor, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 40),
            const Text(
              'Life Changers Touch Ministries Television (LCMTV) is a digital platform dedicated to spreading the gospel of Jesus Christ through premium video content, live broadcasts, and interactive ministry features.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white70, fontSize: 15, height: 1.6),
            ),
            const SizedBox(height: 30),
            const Text(
              'Our mission is to reach the unreached and touch lives globally with the power of the Holy Spirit.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white70, fontSize: 15, height: 1.6),
            ),
            const SizedBox(height: 60),
            const Text(
              '© 2026 Life Changers Touch Ministries.\nAll Rights Reserved.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white24, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}
