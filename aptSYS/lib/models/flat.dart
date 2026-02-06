class Person {
  final String name;
  final String phone;
  final String? nameKey;
  final String? phoneKey;

  Person({required this.name, this.phone = '', this.nameKey, this.phoneKey});
}

class Flat {
  final String number;
  final List<Person> owners;
  String? remoteId; // optional id returned by the backend when saved remotely
  String? formRowId; // optional numeric/form row id returned inside `formFormat` responses
  Map<String, dynamic>? formEntities; // original entities map from server (used for updates)
  List<Map<String, String>>? removedData; // list of removed entity descriptors for update requests

  Flat({required this.number, List<Person>? owners, this.remoteId, this.formRowId, this.formEntities, this.removedData}) : owners = owners ?? [];

  Map<String, dynamic> toMap() => {
        'number': number,
        'remoteId': remoteId,
      'owners': owners.map((o) => {'name': o.name, 'phone': o.phone}).toList(),
        'formEntities': formEntities,
        'removedData': removedData,
      };
}

// DocumentStore removed — use `DocumentProvider` instead for app state management.
