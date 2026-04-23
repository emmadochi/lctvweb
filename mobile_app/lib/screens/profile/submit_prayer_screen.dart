import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';
import '../../providers/prayer_provider.dart';
import '../../providers/auth_provider.dart';

class SubmitPrayerScreen extends StatefulWidget {
  const SubmitPrayerScreen({super.key});

  @override
  State<SubmitPrayerScreen> createState() => _SubmitPrayerScreenState();
}

class _SubmitPrayerScreenState extends State<SubmitPrayerScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _cityController = TextEditingController();
  final _countryController = TextEditingController();
  final _requestController = TextEditingController();
  String _selectedCategory = 'General';
  File? _pickedImage;
  final ImagePicker _imagePicker = ImagePicker();

  final List<String> _categories = [
    'General',
    'Healing',
    'Finance',
    'Family',
    'Careers',
    'Spiritual Growth',
    'Protection',
    'Guidance',
    'Others'
  ];

  @override
  void initState() {
    super.initState();
    // Pre-fill user data if they are logged in
    final auth = context.read<AuthProvider>();
    if (auth.isAuthenticated && auth.user != null) {
      _nameController.text = auth.user!.fullName;
      _emailController.text = auth.user!.email;
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _cityController.dispose();
    _countryController.dispose();
    _requestController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<PrayerProvider>();

    return Scaffold(
      backgroundColor: const Color(0xFF1A1C3E),
      appBar: AppBar(
        title: const Text('Prayer Request', style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildHeader(),
              const SizedBox(height: 30),
              _buildTextField(
                controller: _nameController,
                label: 'Full Name',
                hint: 'Enter your full name',
                icon: Icons.person_outline,
                validator: (val) => val == null || val.isEmpty ? 'Please enter your name' : null,
              ),
              const SizedBox(height: 20),
              _buildTextField(
                controller: _emailController,
                label: 'Email Address',
                hint: 'Ex: name@example.com',
                icon: Icons.email_outlined,
                keyboardType: TextInputType.emailAddress,
                validator: (val) => val == null || !val.contains('@') ? 'Please enter a valid email' : null,
              ),
              const SizedBox(height: 20),
              _buildTextField(
                controller: _phoneController,
                label: 'Phone Number (Optional)',
                hint: 'Ex: +1 234 567 890',
                icon: Icons.phone_outlined,
                keyboardType: TextInputType.phone,
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: _buildTextField(
                      controller: _cityController,
                      label: 'City',
                      hint: 'Enter city',
                      icon: Icons.location_city_outlined,
                      validator: (val) => val == null || val.isEmpty ? 'City required' : null,
                    ),
                  ),
                  const SizedBox(width: 15),
                  Expanded(
                    child: _buildTextField(
                      controller: _countryController,
                      label: 'Country',
                      hint: 'Enter country',
                      icon: Icons.public_outlined,
                      validator: (val) => val == null || val.isEmpty ? 'Country required' : null,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              const Text(
                'Request Category',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
              ),
              const SizedBox(height: 10),
              _buildCategoryDropdown(),
              const SizedBox(height: 25),
              _buildTextField(
                controller: _requestController,
                label: 'Your Prayer Request',
                hint: 'Tell us what you would like us to pray for...',
                maxLines: 6,
                validator: (val) => val == null || val.isEmpty ? 'Please enter your request' : null,
              ),
              const SizedBox(height: 25),
              _buildAttachmentPicker(),
              const SizedBox(height: 30),
              _buildSubmitButton(provider),
              const SizedBox(height: 20),
              _buildFaithVerse(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: const Color(0xFF25284B),
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: Colors.white.withOpacity(0.05)),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFFFB800).withOpacity(0.1),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.front_hand, color: Color(0xFFFFB800), size: 30),
          ),
          const SizedBox(width: 15),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Stand in Agreement',
                  style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                ),
                SizedBox(height: 5),
                Text(
                  'Our prayer team is ready to stand with you in faith for your needs.',
                  style: TextStyle(color: Colors.white70, fontSize: 13),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String label,
    required String hint,
    IconData? icon,
    int maxLines = 1,
    TextInputType? keyboardType,
    String? Function(String?)? validator,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
        ),
        const SizedBox(height: 10),
        TextFormField(
          controller: controller,
          maxLines: maxLines,
          keyboardType: keyboardType,
          validator: validator,
          style: const TextStyle(color: Colors.white),
          decoration: InputDecoration(
            prefixIcon: icon != null ? Icon(icon, color: Colors.white38, size: 20) : null,
            hintText: hint,
            hintStyle: const TextStyle(color: Colors.white24, fontSize: 14),
            filled: true,
            fillColor: const Color(0xFF25284B),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide.none,
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(color: Colors.white.withOpacity(0.05)),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xFFFFB800), width: 1),
            ),
            errorStyle: const TextStyle(color: Colors.redAccent),
          ),
        ),
      ],
    );
  }

  Widget _buildCategoryDropdown() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 15),
      decoration: BoxDecoration(
        color: const Color(0xFF25284B),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.white.withOpacity(0.05)),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<String>(
          value: _selectedCategory,
          dropdownColor: const Color(0xFF25284B),
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
          isExpanded: true,
          icon: const Icon(Icons.keyboard_arrow_down, color: Color(0xFFFFB800)),
          items: _categories.map((String category) {
            return DropdownMenuItem<String>(
              value: category,
              child: Text(category),
            );
          }).toList(),
          onChanged: (val) {
            if (val != null) setState(() => _selectedCategory = val);
          },
        ),
      ),
    );
  }

  Widget _buildSubmitButton(PrayerProvider provider) {
    return SizedBox(
      width: double.infinity,
      height: 55,
      child: ElevatedButton(
        onPressed: provider.isLoading ? null : _handleSubmit,
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xFFFFB800),
          foregroundColor: Colors.black,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          elevation: 5,
          shadowColor: const Color(0xFFFFB800).withOpacity(0.3),
        ),
        child: provider.isLoading
            ? const SizedBox(
                width: 25,
                height: 25,
                child: CircularProgressIndicator(color: Colors.black, strokeWidth: 2),
              )
            : const Text(
                'SUBMIT PRAYER REQUEST',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, letterSpacing: 0.5),
              ),
      ),
    );
  }

  Widget _buildFaithVerse() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: const Color(0xFFFFB800).withOpacity(0.05),
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: const Color(0xFFFFB800).withOpacity(0.1)),
      ),
      child: const Column(
        children: [
          Icon(Icons.format_quote, color: Color(0xFFFFB800), size: 30),
          SizedBox(height: 5),
          Text(
            '"Therefore I tell you, whatever you ask for in prayer, believe that you have received it, and it will be yours."',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Colors.white70,
              fontStyle: FontStyle.italic,
              fontSize: 14,
              height: 1.5,
            ),
          ),
          SizedBox(height: 10),
          Text(
            '- Mark 11:24',
            style: TextStyle(color: Color(0xFFFFB800), fontWeight: FontWeight.bold, fontSize: 13),
          ),
        ],
      ),
    );
  }

  Future<void> _pickImage() async {
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF25284B),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(height: 12),
            Container(
              width: 40, height: 4,
              decoration: BoxDecoration(
                color: Colors.white24,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 20),
            const Text('Select Attachment', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold)),
            const SizedBox(height: 15),
            ListTile(
              leading: const Icon(Icons.photo_camera, color: Color(0xFFFFB800)),
              title: const Text('Take a Photo', style: TextStyle(color: Colors.white)),
              onTap: () async {
                Navigator.pop(ctx);
                final XFile? img = await _imagePicker.pickImage(source: ImageSource.camera, imageQuality: 70);
                if (img != null) setState(() => _pickedImage = File(img.path));
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library, color: Color(0xFFFFB800)),
              title: const Text('Choose from Gallery', style: TextStyle(color: Colors.white)),
              onTap: () async {
                Navigator.pop(ctx);
                final XFile? img = await _imagePicker.pickImage(source: ImageSource.gallery, imageQuality: 70);
                if (img != null) setState(() => _pickedImage = File(img.path));
              },
            ),
            const SizedBox(height: 10),
          ],
        ),
      ),
    );
  }

  Widget _buildAttachmentPicker() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Supporting Document (Optional)',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
        ),
        const SizedBox(height: 6),
        const Text(
          'Attach an image such as a medical test result or any relevant document.',
          style: TextStyle(color: Colors.white38, fontSize: 12),
        ),
        const SizedBox(height: 12),
        if (_pickedImage == null)
          GestureDetector(
            onTap: _pickImage,
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 24),
              decoration: BoxDecoration(
                color: const Color(0xFF25284B),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFFFB800).withOpacity(0.3), style: BorderStyle.solid),
              ),
              child: Column(
                children: [
                  Icon(Icons.cloud_upload_outlined, color: const Color(0xFFFFB800).withOpacity(0.7), size: 36),
                  const SizedBox(height: 8),
                  const Text('Tap to upload image', style: TextStyle(color: Colors.white54, fontSize: 13)),
                  const SizedBox(height: 4),
                  const Text('JPG, PNG up to 5MB', style: TextStyle(color: Colors.white24, fontSize: 11)),
                ],
              ),
            ),
          )
        else
          Stack(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: Image.file(_pickedImage!, width: double.infinity, height: 180, fit: BoxFit.cover),
              ),
              Positioned(
                top: 8, right: 8,
                child: GestureDetector(
                  onTap: () => setState(() => _pickedImage = null),
                  child: Container(
                    padding: const EdgeInsets.all(6),
                    decoration: const BoxDecoration(
                      color: Colors.redAccent,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.close, color: Colors.white, size: 16),
                  ),
                ),
              ),
            ],
          ),
      ],
    );
  }

  void _handleSubmit() async {
    if (!_formKey.currentState!.validate()) return;

    final provider = context.read<PrayerProvider>();
    final data = {
      'full_name': _nameController.text,
      'email': _emailController.text,
      'phone': _phoneController.text,
      'city': _cityController.text,
      'country': _countryController.text,
      'category': _selectedCategory,
      'request_text': _requestController.text,
      if (_pickedImage != null) 'attachment_path': _pickedImage!.path,
      if (_pickedImage != null) 'attachment_name': _pickedImage!.path.split('/').last,
    };

    final success = await provider.submitRequest(data);
    if (success) {
      if (!mounted) return;
      _showSuccessDialog();
      _requestController.clear();
      _phoneController.clear();
      _cityController.clear();
      _countryController.clear();
      setState(() => _pickedImage = null);
    } else {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(provider.error ?? 'Failed to submit request'),
          backgroundColor: Colors.redAccent,
        ),
      );
    }
  }

  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => Dialog(
        backgroundColor: Colors.transparent,
        child: Container(
          padding: const EdgeInsets.all(30),
          decoration: BoxDecoration(
            color: const Color(0xFF25284B),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFFFB800).withOpacity(0.3)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: const BoxDecoration(
                  color: Color(0xFF1A1C3E),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.check_circle_rounded,
                  color: Color(0xFFFFB800),
                  size: 80,
                ),
              ),
              const SizedBox(height: 25),
              const Text(
                'Thank You',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 24,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 15),
              const Text(
                'Your prayer request has been received. Our prayer team will stand with you in faith. God bless you.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Colors.white70,
                  fontSize: 14,
                  height: 1.5,
                ),
              ),
              const SizedBox(height: 30),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.pop(context); // Close dialog
                    Navigator.pop(context); // Go back to profile
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFFFB800),
                    foregroundColor: Colors.black,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text('CONTINUE', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
