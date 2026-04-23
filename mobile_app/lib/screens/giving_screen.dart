import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';
import '../providers/donation_provider.dart';
import '../providers/auth_provider.dart';
import 'package:flutter_paystack/flutter_paystack.dart';

class GivingScreen extends StatefulWidget {
  const GivingScreen({super.key});

  @override
  State<GivingScreen> createState() => _GivingScreenState();
}

class _GivingScreenState extends State<GivingScreen> {
  final _amountController = TextEditingController();
  final _notesController = TextEditingController();
  String _selectedMethod = 'paystack';
  String _selectedProvider = 'paystack';
  int? _selectedCampaignId;
  String? _selectedCategory;
  String _selectedCurrency = 'NGN';
  XFile? _receiptFile;

  final plugin = PaystackPlugin();
  bool _isPaystackInitialized = false;

  String _getReference() {
    String platform = (Platform.isIOS) ? 'iOS' : 'Android';
    final date = DateTime.now().millisecondsSinceEpoch;
    return 'LCMTV_${platform}_$date';
  }

  final List<Map<String, String>> _currencies = [
    {'code': 'USD', 'symbol': '\$'},
    {'code': 'GBP', 'symbol': '£'},
    {'code': 'EUR', 'symbol': '€'},
    {'code': 'NGN', 'symbol': '₦'},
  ];

  String _getCurrencySymbol(String code) {
    return _currencies.firstWhere((c) => c['code'] == code)['symbol']!;
  }

  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      context.read<DonationProvider>().loadCampaigns();
      context.read<DonationProvider>().loadPaymentSettings();
    });
  }

  @override
  void dispose() {
    _amountController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<DonationProvider>();
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      backgroundColor: const Color(0xFF1A1C3E),
      appBar: AppBar(
        title: const Text('Give Online', style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
      ),
      body: provider.isLoading && provider.campaigns.isEmpty
          ? const Center(child: CircularProgressIndicator(color: Color(0xFFFFB800)))
          : SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildHeader(),
                  const SizedBox(height: 25),
                  _buildAmountInput(),
                  const SizedBox(height: 25),
                  _buildCategoryAndNotes(provider),
                  const SizedBox(height: 25),
                  _buildCampaigns(provider),
                  const SizedBox(height: 25),
                  _buildPaymentMethods(provider),
                  const SizedBox(height: 30),
                  _buildManualDetails(provider),
                  const SizedBox(height: 40),
                  _buildSubmitButton(provider, auth),
                ],
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
      ),
      child: Row(
        children: [
          const Icon(Icons.favorite, color: Color(0xFFFFB800), size: 40),
          const SizedBox(width: 15),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                Text(
                  'Your Giving Matters',
                  style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                ),
                SizedBox(height: 5),
                Text(
                  'Support our mission to spread the gospel globally.',
                  style: TextStyle(color: Colors.white70, fontSize: 13),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAmountInput() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Contribution Amount', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              flex: 3,
              child: TextField(
                controller: _amountController,
                keyboardType: TextInputType.number,
                style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold),
                decoration: InputDecoration(
                  prefixText: _getCurrencySymbol(_selectedCurrency),
                  prefixStyle: const TextStyle(color: Color(0xFFFFB800), fontSize: 24, fontWeight: FontWeight.bold),
                  hintText: '0.00',
                  hintStyle: const TextStyle(color: Colors.white24),
                  filled: true,
                  fillColor: const Color(0xFF25284B),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              flex: 1,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10),
                decoration: BoxDecoration(
                  color: const Color(0xFF25284B),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<String>(
                    value: _selectedCurrency,
                    dropdownColor: const Color(0xFF25284B),
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                    items: _currencies.map((c) {
                      return DropdownMenuItem(
                        value: c['code'],
                        child: Text(c['code']!),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) setState(() => _selectedCurrency = val);
                    },
                  ),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildCategoryAndNotes(DonationProvider provider) {
    final givingTypes = provider.paymentSettings['giving_type'] as List? ?? [];
    
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Giving Category', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        const SizedBox(height: 12),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 15),
          decoration: BoxDecoration(
            color: const Color(0xFF25284B),
            borderRadius: BorderRadius.circular(12),
          ),
          child: DropdownButtonHideUnderline(
            child: DropdownButton<String>(
              value: _selectedCategory,
              hint: const Text('Select Category', style: TextStyle(color: Colors.white24)),
              dropdownColor: const Color(0xFF25284B),
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
              isExpanded: true,
              items: givingTypes.map<DropdownMenuItem<String>>((t) {
                final value = t['setting_value'] as String;
                return DropdownMenuItem<String>(
                  value: value,
                  child: Text(value),
                );
              }).toList(),
              onChanged: (val) {
                setState(() => _selectedCategory = val);
              },
            ),
          ),
        ),
        const SizedBox(height: 20),
        const Text('Notes (Optional)', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        const SizedBox(height: 12),
        TextField(
          controller: _notesController,
          maxLines: 2,
          style: const TextStyle(color: Colors.white),
          decoration: InputDecoration(
            hintText: 'Write a short description about your giving...',
            hintStyle: const TextStyle(color: Colors.white24, fontSize: 13),
            filled: true,
            fillColor: const Color(0xFF25284B),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
          ),
        ),
      ],
    );
  }

  Widget _buildCampaigns(DonationProvider provider) {
    if (provider.campaigns.isEmpty) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Active Campaigns', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        const SizedBox(height: 12),
        SizedBox(
          height: 120,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            itemCount: provider.campaigns.length,
            itemBuilder: (context, index) {
              final campaign = provider.campaigns[index];
              final isSelected = _selectedCampaignId == campaign.id;
              return GestureDetector(
                onTap: () => setState(() => _selectedCampaignId = isSelected ? null : campaign.id),
                child: Container(
                  width: 200,
                  margin: const EdgeInsets.only(right: 15),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: isSelected ? const Color(0xFFFFB800) : const Color(0xFF25284B),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: isSelected ? Colors.white : Colors.transparent, width: 2),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        campaign.title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(color: isSelected ? Colors.black : Colors.white, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 8),
                      LinearProgressIndicator(
                        value: campaign.progressPercentage / 100,
                        backgroundColor: Colors.white24,
                        color: isSelected ? Colors.black : const Color(0xFFFFB800),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _buildPaymentMethods(DonationProvider provider) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Payment Method', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        const SizedBox(height: 12),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            _buildMethodChip('paystack', 'Paystack', Icons.payment),
            _buildMethodChip('card', 'Credit Card', Icons.credit_card),
            _buildMethodChip('paypal', 'PayPal', Icons.paypal),
            _buildMethodChip('bank_transfer', 'Bank', Icons.account_balance),
            _buildMethodChip('crypto', 'Crypto', Icons.currency_bitcoin),
          ],
        ),
      ],
    );
  }

  Widget _buildMethodChip(String value, String label, IconData icon) {
    final isSelected = _selectedMethod == value;
    return GestureDetector(
      onTap: () => setState(() => _selectedMethod = value),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFFFB800) : const Color(0xFF25284B),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: isSelected ? Colors.white : Colors.transparent),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 18, color: isSelected ? Colors.black : Colors.white70),
            const SizedBox(width: 8),
            Text(label, style: TextStyle(color: isSelected ? Colors.black : Colors.white70, fontWeight: FontWeight.bold, fontSize: 13)),
          ],
        ),
      ),
    );
  }

  Widget _buildManualDetails(DonationProvider provider) {
    if (_selectedMethod == 'bank_transfer') {
      final banks = provider.paymentSettings['bank'] as List? ?? [];
      return _buildDetailSection('Bank Account Details', banks);
    }
    if (_selectedMethod == 'crypto') {
      final wallets = provider.paymentSettings['crypto'] as List? ?? [];
      return _buildDetailSection('Crypto Wallet Addresses', wallets);
    }
    return const SizedBox.shrink();
  }

  Widget _buildDetailSection(String title, List items) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: const Color(0xFF131530), borderRadius: BorderRadius.circular(12)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(color: Color(0xFFFFB800), fontWeight: FontWeight.bold)),
          const SizedBox(height: 10),
          ...items.map((item) => Padding(
                padding: const EdgeInsets.only(bottom: 15),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(item['setting_key'], style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Expanded(
                          child: Text(item['setting_value'], style: const TextStyle(color: Colors.white70, fontSize: 13)),
                        ),
                        IconButton(
                          icon: const Icon(Icons.copy, color: Color(0xFFFFB800), size: 18),
                          onPressed: () {
                            Clipboard.setData(ClipboardData(text: item['setting_value']));
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text('${item['setting_key']} copied to clipboard'),
                                duration: const Duration(seconds: 2),
                                behavior: SnackBarBehavior.floating,
                                backgroundColor: const Color(0xFF25284B),
                              ),
                            );
                          },
                          tooltip: 'Copy',
                        ),
                      ],
                    ),
                  ],
                ),
              )),
          const Divider(color: Colors.white10),
          const SizedBox(height: 10),
          const Text('Note: After transfer, please click the button below to upload your receipt.', 
            style: TextStyle(color: Colors.white38, fontSize: 11, fontStyle: FontStyle.italic)),
        ],
      ),
    );
  }

  Widget _buildSubmitButton(DonationProvider provider, AuthProvider auth) {
    return SizedBox(
      width: double.infinity,
      height: 55,
      child: ElevatedButton(
        onPressed: provider.isLoading ? null : () => _handleSubmit(provider, auth),
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xFFFFB800),
          foregroundColor: Colors.black,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
        child: provider.isLoading 
          ? const CircularProgressIndicator(color: Colors.black)
          : const Text('PROCEED TO GIVE', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
      ),
    );
  }

  void _handleSubmit(DonationProvider provider, AuthProvider auth) async {
    final amount = double.tryParse(_amountController.text);
    if (amount == null || amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please enter a valid amount')));
      return;
    }

    if (!auth.isAuthenticated) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please login to continue')));
      return;
    }

    if (_selectedMethod == 'bank_transfer' || _selectedMethod == 'crypto') {
      // Show report dialog for manual transfer
      _showReportDialog(provider, auth, amount);
      return;
    }

    if (_selectedMethod == 'card' || _selectedMethod == 'paystack') {
      final gateways = provider.paymentSettings['gateway'] as List? ?? [];
      final paystackConfig = gateways.firstWhere((g) => g['setting_key'] == 'paystack_public_key', orElse: () => null);
      
      // Check if config exists and is active (handling both int and string from API)
      bool isActive = paystackConfig != null && 
                     (paystackConfig['is_active'].toString() == '1' || paystackConfig['is_active'] == true);

      if (isActive) {
        _processPaystack(provider, auth, amount, paystackConfig['setting_value']);
        return;
      } else {
        String msg = _selectedMethod == 'paystack' 
          ? 'Paystack gateway is not currently configured or active.' 
          : 'Credit Card gateway is not currently configured.';
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
        return;
      }
    }

    // Logic for PayPal / others would go here
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Gateway integration coming soon. Use Bank/Crypto for now.')));
  }

  Future<void> _processPaystack(DonationProvider provider, AuthProvider auth, double amount, String publicKey) async {
    try {
      if (!_isPaystackInitialized) {
        await plugin.initialize(publicKey: publicKey);
        _isPaystackInitialized = true;
      }

      Charge charge = Charge()
        ..amount = (amount * 100).toInt()
        ..reference = _getReference()
        ..email = auth.user?.email ?? 'guest@lcmtv.com'
        ..currency = _selectedCurrency
        ..putCustomField('donation_purpose', _selectedCategory ?? 'General');

      CheckoutResponse response = await plugin.checkout(
        context,
        method: CheckoutMethod.card, // Defaults to card
        charge: charge,
        fullscreen: false,
        logo: const Icon(Icons.favorite, color: Color(0xFFFFB800), size: 40),
      );

      if (response.status == true) {
        final data = {
          'user_id': auth.user?.id,
          'donor_name': auth.user?.fullName ?? 'Guest',
          'donor_email': auth.user?.email ?? 'guest@lcmtv.com',
          'amount': amount.toString(),
          'currency': _selectedCurrency,
          'payment_method': 'card',
          'payment_provider': 'paystack',
          'transaction_id': response.reference,
          'transaction_status': 'completed',
          'donation_purpose': _selectedCategory ?? 'General',
          'campaign_id': _selectedCampaignId?.toString() ?? '',
          'notes': _notesController.text,
        };
        
        bool success = await provider.submitDonation(data);
        if (success) {
          if (!mounted) return;
          _amountController.clear();
          _notesController.clear();
          setState(() { _selectedCategory = null; });
          _showSuccessDialog();
        } else {
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(provider.error ?? 'Failed to record transaction.')),
          );
        }
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Transaction ${response.message}')),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Payment Error: $e')),
      );
    }
  }

  void _showReportDialog(DonationProvider provider, AuthProvider auth, double amount) {
    _receiptFile = null;
    final picker = ImagePicker();

    showDialog(
      context: context,
      builder: (context) => Consumer<DonationProvider>(
        builder: (context, provider, child) => StatefulBuilder(
          builder: (context, setDialogState) => AlertDialog(
            backgroundColor: const Color(0xFF25284B),
            title: const Text('Upload Receipt', style: TextStyle(color: Colors.white)),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text('Please upload a screenshot or photo of your transfer receipt for verification.', 
                  style: TextStyle(color: Colors.white70, fontSize: 13)),
                const SizedBox(height: 20),
                if (_receiptFile != null)
                  Stack(
                    children: [
                      Container(
                        height: 150,
                        width: double.infinity,
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(10),
                          image: DecorationImage(
                            image: FileImage(File(_receiptFile!.path)),
                            fit: BoxFit.cover,
                          ),
                        ),
                      ),
                      Positioned(
                        right: 5,
                        top: 5,
                        child: GestureDetector(
                          onTap: provider.isLoading ? null : () => setDialogState(() => _receiptFile = null),
                          child: Container(
                            padding: const EdgeInsets.all(4),
                            decoration: const BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
                            child: const Icon(Icons.close, color: Colors.white, size: 16),
                          ),
                        ),
                      ),
                    ],
                  )
                else
                  Row(
                    children: [
                      Expanded(
                        child: _buildPickerOption(
                          icon: Icons.camera_alt,
                          label: 'Camera',
                          onTap: () async {
                            final file = await picker.pickImage(source: ImageSource.camera);
                            if (file != null) setDialogState(() => _receiptFile = file);
                          },
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _buildPickerOption(
                          icon: Icons.photo_library,
                          label: 'Gallery',
                          onTap: () async {
                            final file = await picker.pickImage(source: ImageSource.gallery);
                            if (file != null) setDialogState(() => _receiptFile = file);
                          },
                        ),
                      ),
                    ],
                  ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: provider.isLoading ? null : () => Navigator.pop(context),
                child: const Text('Cancel', style: TextStyle(color: Colors.white38)),
              ),
              ElevatedButton(
                onPressed: _receiptFile == null || provider.isLoading
                    ? null
                    : () async {
                        final data = {
                          'user_id': auth.user?.id,
                          'donor_name': auth.user?.fullName ?? 'Guest',
                          'donor_email': auth.user?.email ?? '',
                          'amount': amount.toString(),
                          'currency': _selectedCurrency,
                          'payment_method': _selectedMethod,
                          'donation_purpose': _selectedCategory ?? 'General',
                          'campaign_id': _selectedCampaignId?.toString() ?? '',
                          'notes': _notesController.text,
                        };
                        
                        final success = await provider.reportManualTransfer(data, receiptFile: _receiptFile);
                        if (success) {
                          if (!mounted) return;
                          Navigator.pop(context); // Close upload dialog
                          _showSuccessDialog();   // Show success dialog
                          _amountController.clear();
                          _notesController.clear();
                          setState(() {
                            _selectedCategory = null;
                            _receiptFile = null;
                          });
                        } else {
                          if (!mounted) return;
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text(provider.error ?? 'Failed to upload receipt. Please try again.'),
                              backgroundColor: Colors.redAccent,
                              behavior: SnackBarBehavior.floating,
                            ),
                          );
                        }
                      },
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFFFB800),
                  minimumSize: const Size(100, 45),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: provider.isLoading 
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black))
                  : const Text('Upload', style: TextStyle(color: Colors.black, fontWeight: FontWeight.bold)),
              ),
            ],
          ),
        ),
      ),
    );
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
                'Submission Successful',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 15),
              const Text(
                'Thank you for your generous gift. Your receipt has been uploaded and is being verified. You will see it in your history shortly.',
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
                  onPressed: () => Navigator.pop(context),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFFFB800),
                    foregroundColor: Colors.black,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text(
                    'CONTINUE',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPickerOption({required IconData icon, required String label, required VoidCallback onTap}) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 20),
        decoration: BoxDecoration(
          color: const Color(0xFF131530).withOpacity(0.5),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: Colors.white10),
        ),
        child: Column(
          children: [
            Icon(icon, color: const Color(0xFFFFB800), size: 30),
            const SizedBox(height: 8),
            Text(label, style: const TextStyle(color: Colors.white70, fontSize: 12)),
          ],
        ),
      ),
    );
  }
}
