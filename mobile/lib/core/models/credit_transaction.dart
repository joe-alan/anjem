class CreditTransaction {
  final int id;
  final String type;
  final int amount;
  final int balanceBefore;
  final int balanceAfter;
  final String? description;
  final int? rideId;
  final DateTime createdAt;

  const CreditTransaction({
    required this.id,
    required this.type,
    required this.amount,
    required this.balanceBefore,
    required this.balanceAfter,
    this.description,
    this.rideId,
    required this.createdAt,
  });

  factory CreditTransaction.fromJson(Map<String, dynamic> json) {
    return CreditTransaction(
      id: json['id'] as int,
      type: json['type'] as String,
      amount: json['amount'] as int,
      balanceBefore: json['balance_before'] as int,
      balanceAfter: json['balance_after'] as int,
      description: json['description'] as String?,
      rideId: json['ride_id'] as int?,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }
}
