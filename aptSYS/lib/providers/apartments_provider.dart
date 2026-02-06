import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../models/apartment.dart';

class ApartmentsProvider with ChangeNotifier {
  final String baseUrl;

  ApartmentsProvider(this.baseUrl);

  bool _isLoading = false;
  String? _error;
  List<Apartment> _items = [];
  Apartment? _selected;

  Apartment? get selectedApartment => _selected;

  void setSelectedApartment(Apartment? a) {
    _selected = a;
    if (a != null && kDebugMode) {
      try {
        print('ApartmentsProvider: selected apartment id=${a.id} op_key=${a.opKey} code=${a.code}');
      } catch (_) {}
    }
    notifyListeners();
  }

  bool get isLoading => _isLoading;
  String? get error => _error;
  List<Apartment> get items => List.unmodifiable(_items);

  Future<void> fetchApartments([String? token]) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final uri = Uri.parse('$baseUrl/api/v1/get-apartments');
      final headers = <String, String>{'Accept': 'application/json'};
      if (token != null && token.isNotEmpty) {
        headers['Authorization'] = 'Bearer $token';
      }

      final resp = await http.get(uri, headers: headers).timeout(Duration(seconds: 8));
      if (resp.statusCode == 200) {
        final data = json.decode(resp.body);
        if (data is List) {
          _items = data.map((e) => Apartment.fromJson(e as Map<String, dynamic>)).toList();
        } else if (data is Map && data['data'] is List) {
          _items = (data['data'] as List).map((e) => Apartment.fromJson(e as Map<String, dynamic>)).toList();
        } else {
          _error = 'Unexpected API response format';
        }
      } else {
        _error = 'Server returned ${resp.statusCode}';
      }
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}
