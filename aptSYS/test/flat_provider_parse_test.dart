import 'package:flutter_test/flutter_test.dart';
import 'package:apt_sys/providers/flat_provider.dart';

void main() {
  test('parseDocumentsBody parses sample response', () {
    final provider = FlatProvider('http://localhost');

    const sample = r'''{
    "data": [
        {
            "id": "429d1304-1f71-4ffe-bb1f-f7aa2e39c107",
            "type": "op-doc-flat",
            "cur": "₺",
            "balance_pure": null,
            "balance": null,
            "status": "doc_trans_created**Dosya Sisteme Eklendi**-",
            "main_attr": "[{\"Key\":\"cont_name**flatcontgroup**1770368335651-0\",\"Value\":\"dfasd\"},{\"Key\":\"cont_phone**flatcontgroup**1770368335651-0\",\"Value\":\"322-342-3423\"},{\"Key\":\"title\",\"Value\":\"dfasdf\"}]"
        },
        {
            "id": "2c64131c-8970-4f22-ac4e-6d029359f19e",
            "type": "op-doc-flat",
            "cur": "₺",
            "balance_pure": null,
            "balance": null,
            "status": "doc_trans_created**Dosya Sisteme Eklendi**-",
            "main_attr": "[{\"Key\":\"title\",\"Value\":\"B-8\"}]"
        }
    ],
    "pageCount": 2,
    "totalCount": 17,
    "filteredCount": 10,
    "last_page": 2
}
''';

    final flats = provider.parseDocumentsBody(sample);
    expect(flats.length, 2);
    expect(flats[0].number, equals('dfasdf'));
    expect(flats[0].owners.length, 1);
    expect(flats[0].owners[0].phone, equals('322-342-3423'));
    expect(flats[1].number, equals('B-8'));
  });
}
