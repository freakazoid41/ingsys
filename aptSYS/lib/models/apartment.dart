class Apartment {
  final int id;
  final String title;
  final String address;
  final double price;

  Apartment({required this.id, required this.title, required this.address, required this.price});

  factory Apartment.fromJson(Map<String, dynamic> json) {
    return Apartment(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      title: json['title'] as String? ?? '',
      address: json['address'] as String? ?? '',
      price: (json['price'] is num) ? (json['price'] as num).toDouble() : double.tryParse(json['price'].toString()) ?? 0.0,
    );
  }
}
