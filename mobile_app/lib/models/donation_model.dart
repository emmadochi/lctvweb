class DonationModel {
  final int? id;
  final int? userId;
  final int? donorId;
  final int? campaignId;
  final double amount;
  final String currency;
  final String paymentMethod;
  final String? paymentProvider;
  final String? transactionId;
  final String transactionStatus;
  final bool isRecurring;
  final String? recurringInterval;
  final String donationType;
  final String donationPurpose;
  final bool isAnonymous;
  final String? notes;
  final DateTime? createdAt;

  DonationModel({
    this.id,
    this.userId,
    this.donorId,
    this.campaignId,
    required this.amount,
    this.currency = 'USD',
    required this.paymentMethod,
    this.paymentProvider,
    this.transactionId,
    this.transactionStatus = 'pending',
    this.isRecurring = false,
    this.recurringInterval,
    this.donationType = 'general',
    this.donationPurpose = '',
    this.isAnonymous = false,
    this.notes,
    this.createdAt,
  });

  factory DonationModel.fromJson(Map<String, dynamic> json) {
    return DonationModel(
      id: json['id'],
      userId: json['user_id'],
      donorId: json['donor_id'],
      campaignId: json['campaign_id'],
      amount: (json['amount'] is int) ? (json['amount'] as int).toDouble() : (json['amount'] ?? 0.0),
      currency: json['currency'] ?? 'USD',
      paymentMethod: json['payment_method'] ?? '',
      paymentProvider: json['payment_provider'],
      transactionId: json['transaction_id'],
      transactionStatus: json['transaction_status'] ?? 'pending',
      isRecurring: json['is_recurring'] == 1 || json['is_recurring'] == true,
      recurringInterval: json['recurring_interval'],
      donationType: json['donation_type'] ?? 'general',
      donationPurpose: json['donation_purpose'] ?? '',
      isAnonymous: json['is_anonymous'] == 1 || json['is_anonymous'] == true,
      notes: json['notes'],
      createdAt: json['created_at'] != null ? DateTime.parse(json['created_at']) : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (id != null) 'id': id,
      if (userId != null) 'user_id': userId,
      if (donorId != null) 'donor_id': donorId,
      if (campaignId != null) 'campaign_id': campaignId,
      'amount': amount,
      'currency': currency,
      'payment_method': paymentMethod,
      'payment_provider': paymentProvider,
      'transaction_id': transactionId,
      'transaction_status': transactionStatus,
      'is_recurring': isRecurring ? 1 : 0,
      'recurring_interval': recurringInterval,
      'donation_type': donationType,
      'donation_purpose': donationPurpose,
      'is_anonymous': isAnonymous ? 1 : 0,
      'notes': notes,
    };
  }
}

class DonationCampaign {
  final int id;
  final String title;
  final String? description;
  final double goalAmount;
  final double currentAmount;
  final String currency;
  final String? imageUrl;
  final double progressPercentage;

  DonationCampaign({
    required this.id,
    required this.title,
    this.description,
    required this.goalAmount,
    required this.currentAmount,
    this.currency = 'USD',
    this.imageUrl,
    required this.progressPercentage,
  });

  factory DonationCampaign.fromJson(Map<String, dynamic> json) {
    return DonationCampaign(
      id: json['id'],
      title: json['title'] ?? '',
      description: json['description'],
      goalAmount: (json['goal_amount'] is int) ? (json['goal_amount'] as int).toDouble() : (json['goal_amount'] ?? 0.0),
      currentAmount: (json['current_amount'] is int) ? (json['current_amount'] as int).toDouble() : (json['current_amount'] ?? 0.0),
      currency: json['currency'] ?? 'USD',
      imageUrl: json['image_url'],
      progressPercentage: (json['progress_percentage'] is int) ? (json['progress_percentage'] as int).toDouble() : (json['progress_percentage'] ?? 0.0),
    );
  }
}
