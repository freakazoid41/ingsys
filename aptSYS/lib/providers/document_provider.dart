import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../models/flat.dart';

class DocumentProvider with ChangeNotifier {
  final String baseUrl;

  DocumentProvider(this.baseUrl);

  final List<Flat> _items = [];
  String? _nextPageKey;
  int? _pageCount;

  /// The page key returned by the last fetch; pass this to the next request
  String? get nextPageKey => _nextPageKey;
  int? get pageCount => _pageCount;

  List<Flat> get items => List.unmodifiable(_items);

  void addLocal(Flat f) {
    _items.add(f);
    notifyListeners();
  }

  void removeAt(int index) {
    _items.removeAt(index);
    notifyListeners();
  }

  void clear() {
    _items.clear();
    notifyListeners();
  }

  /// Create a flat on the remote API. Returns true on success.
  Future<bool> createRemote(Flat flat, String grpCode, String bearerToken, {String formTag = 'op-doc-flat-form', String typeKey = 'op-doc-flat'}) async {
    // If the flat has a remoteId, send the update to that document URL
    String uriStr = '$baseUrl/api/v1/document';
    if (flat.remoteId != null && flat.remoteId!.isNotEmpty) {
      uriStr = '$baseUrl/api/v1/document/${flat.remoteId}';
    }
    final uri = Uri.parse(uriStr);
    final timestamp = DateTime.now().millisecondsSinceEpoch;
    final entryKey = '$formTag**new-$timestamp';

    Map<String, dynamic> entities = {};
    // If caller provided formEntities (for custom forms like kasa), prefer them
    if (flat.formEntities != null && flat.formEntities!.isNotEmpty) {
      entities = Map<String, dynamic>.from(flat.formEntities!);
      // ensure title is present/updated
      entities['title'] = flat.number;
    } else {
      entities = <String, dynamic>{'title': flat.number};
      for (var i = 0; i < flat.owners.length; i++) {
        final gid = timestamp + i + 1;
        entities['cont_name**flatcontgroup**$gid-$i'] = flat.owners[i].name;
        entities['cont_phone**flatcontgroup**$gid-$i'] = flat.owners[i].phone;
      }
    }

    final data = {
      'dynamicF': {entryKey: {'entities': entities, 'tag': formTag}},
      'files': {},
      'removedData': flat.removedData ?? [],
      'grp_code': grpCode,
      'op_key': grpCode,
      'typeKey': typeKey
    };

    try {
      final req = http.MultipartRequest('POST', uri);
          req.fields['data'] = jsonEncode(data);
          req.fields['grp_code'] = grpCode;
          req.fields['op_key'] = grpCode;
      req.headers['Authorization'] = 'Bearer $bearerToken';

      if (kDebugMode) {
        try {
          print('createRemote: POST ${uri.toString()}');
              print('createRemote: grp_code (field) = ${req.fields['grp_code']}');
              print('createRemote: op_key (field) = ${req.fields['op_key']}');
              final parsed = jsonDecode(req.fields['data']!);
              print('createRemote: data.grp_code = ${parsed['grp_code']}');
              print('createRemote: data.op_key = ${parsed['op_key']}');
        } catch (e) {
          print('createRemote: debug print failed: $e');
        }
      }

      final streamed = await req.send();
      final resp = await http.Response.fromStream(streamed);

      if (resp.statusCode >= 200 && resp.statusCode < 300) {
        // Optionally parse response to extract an id
        try {
          final body = jsonDecode(resp.body);
          if (body is Map && body['id'] != null) {
            flat.remoteId = body['id'].toString();
          }
        } catch (_) {}

        addLocal(flat);
        return true;
      }
      return false;
    } catch (e) {
      if (kDebugMode) print('createRemote failed: $e');
      return false;
    }
  }

  /// Update an existing flat on the remote API using the form row id when available.
  Future<bool> updateRemote(Flat flat, String grpCode, String bearerToken, {String? rowKey, String formTag = 'op-doc-flat-form', String typeKey = 'op-doc-flat'}) async {
    // If no rowKey provided but flat has formRowId, use that
    final rk = rowKey ?? flat.formRowId;
    if (rk == null || rk.isEmpty) {
      // fallback to create
      return createRemote(flat, grpCode, bearerToken);
    }
    if (kDebugMode) {
      print('updateRemote: flat as JSON: ${jsonEncode(flat.toMap())}');
    }
    String uriStr = '$baseUrl/api/v1/document';
    if (flat.remoteId != null && flat.remoteId!.isNotEmpty) {
      uriStr = '$baseUrl/api/v1/document/${flat.remoteId}';
    }
    final uri = Uri.parse(uriStr);
    final entryKey = '$formTag**$rk';

    Map<String, dynamic> entities = {};
    // If original formEntities are available (from GET), reuse their keys and replace values
    if (flat.formEntities != null && flat.formEntities!.isNotEmpty) {
      // start with a copy
      entities = Map<String, dynamic>.from(flat.formEntities!);
      // remove any keys that were marked removed so they are not present in entities
      if (flat.removedData != null) {
        for (final rd in flat.removedData!) {
          final key = rd['key'];
          if (key != null && entities.containsKey(key)) entities.remove(key);
        }
      }
      // ensure title is updated
      entities['title'] = flat.number;
      // For existing entries, only use per-owner assigned keys (owner.nameKey) when still present.
      // Do NOT shift other existing keys into different owner slots — owners without a preserved
      // key will get new keys. This prevents cloning/shifting when an owner is removed.
      int nextNewIndex = 0;
      for (var i = 0; i < flat.owners.length; i++) {
        final owner = flat.owners[i];
        String? ownerNameKey;
        try {
          ownerNameKey = (owner as dynamic).nameKey as String?;
        } catch (_) {
          ownerNameKey = null;
        }

        if (ownerNameKey != null && ownerNameKey.isNotEmpty && entities.containsKey(ownerNameKey)) {
          // use the preserved key
          entities[ownerNameKey] = owner.name;
          final phoneKey = ownerNameKey.replaceFirst('cont_name', 'cont_phone');
          if (!entities.containsKey(phoneKey)) entities[phoneKey] = '';
          entities[phoneKey] = owner.phone;
        } else {
          // create new keys for this owner (either no preserved key, or it was removed)
          final gid = DateTime.now().millisecondsSinceEpoch + nextNewIndex + 1;
          final nameKey = 'cont_name**flatcontgroup**$gid-$i';
          final phoneKey = 'cont_phone**flatcontgroup**$gid-$i';
          entities[nameKey] = owner.name;
          entities[phoneKey] = owner.phone;
          nextNewIndex++;
        }
      }
    } else {
      // create new keys for create/update when no original keys exist
      entities = <String, dynamic>{'title': flat.number};
      for (var i = 0; i < flat.owners.length; i++) {
        final gid = DateTime.now().millisecondsSinceEpoch + i + 1;
        entities['cont_name**flatcontgroup**$gid-$i'] = flat.owners[i].name;
        entities['cont_phone**flatcontgroup**$gid-$i'] = flat.owners[i].phone;
      }
    }

    final data = {
      'dynamicF': {entryKey: {'entities': entities, 'tag': formTag}},
      'files': {},
      'removedData': flat.removedData ?? [],
      'grp_code': grpCode,
      'op_key': grpCode,
      'typeKey': typeKey
    };

    try {
      final isUpdate = flat.remoteId != null && flat.remoteId!.isNotEmpty;
      final method = isUpdate ? 'PUT' : 'POST';
      final req = http.MultipartRequest(method, uri);
      req.fields['data'] = jsonEncode(data);
      req.fields['grp_code'] = grpCode;
      req.fields['op_key'] = grpCode;
      req.headers['Authorization'] = 'Bearer $bearerToken';

      if (kDebugMode) {
        try {
          print('updateRemote: $method ${uri.toString()}');
          print('updateRemote: entryKey=$entryKey');
          print('updateRemote: headers=${req.headers}');
          print('updateRemote: fields=${req.fields}');
          // Also log the JSON payload for easier inspection
          try {
            final pretty = JsonEncoder.withIndent('  ').convert(jsonDecode(req.fields['data']!));
            print('updateRemote: data (pretty):\n$pretty');
          } catch (_) {
            print('updateRemote: data=${req.fields['data']}');
          }
        } catch (e) {
          print('updateRemote debug failed: $e');
        }
      }

      final streamed = await req.send();
      final resp = await http.Response.fromStream(streamed);

      if (resp.statusCode >= 200 && resp.statusCode < 300) {
        // Optionally update remoteId if returned
        try {
          final body = jsonDecode(resp.body);
          if (body is Map && body['id'] != null) {
            flat.remoteId = body['id'].toString();
          }
        } catch (_) {}

        // update local store: replace existing item with same remoteId if present
        final idx = _items.indexWhere((x) => x.remoteId != null && x.remoteId == flat.remoteId);
        if (idx >= 0) {
          _items[idx] = flat;
        } else {
          addLocal(flat);
        }
        notifyListeners();
        return true;
      }
      if (kDebugMode) print('updateRemote status=${resp.statusCode} body=${resp.body}');
      return false;
    } catch (e) {
      if (kDebugMode) print('updateRemote failed: $e');
      return false;
    }
  }

  /// Fetch documents/flats for a group from the API.
  /// Note: backend endpoint/response shape may differ; adapt as needed.
    Future<String?> fetchForGroup(String grpCode,
      {String? bearerToken, String? pageKey, int page = 1, int limit = 10, bool append = false}) async {
    try {
      final uri = Uri.parse('$baseUrl/api/v1/table/documents');

      final req = http.MultipartRequest('POST', uri);
      final tableReq = {
        'scale': {'limit': limit, 'page': page},
        'filter': [
          {'key': 'form-type', 'type': '=', 'value': 'op-doc-flat-form'},
          {'key': 'type', 'type': '=', 'value': 'op-doc-flat'}
        ]
      };

      // include page key inside tableReq for backends that expect it there
      if (pageKey != null && pageKey.isNotEmpty) {
        tableReq['pageKey'] = pageKey;
        tableReq['page_key'] = pageKey;
      }

      req.fields['tableReq'] = jsonEncode(tableReq);
      req.fields['grp_code'] = grpCode;
      if (pageKey != null && pageKey.isNotEmpty) req.fields['pageKey'] = pageKey;

      if (bearerToken != null && bearerToken.isNotEmpty) {
        req.headers['Authorization'] = 'Bearer $bearerToken';
      }

      if (kDebugMode) {
        try {
          print('fetchForGroup: POST ${uri.toString()}');
          print('fetchForGroup: tableReq=${req.fields['tableReq']}');
          print('fetchForGroup: grp_code=${req.fields['grp_code']}');
        } catch (e) {
          print('fetchForGroup debug failed: $e');
        }
      }

      final streamed = await req.send();
      final resp = await http.Response.fromStream(streamed);

      if (resp.statusCode >= 200 && resp.statusCode < 300) {
        // parse items and any pagination key
        final decoded = _parseDocumentsResponse(resp.body);
        final List<Flat> parsed = decoded['items'] as List<Flat>;
        final String? nextKey = decoded['pageKey'] as String?;
        final int? pageCount = decoded['pageCount'] as int?;

        if (!append) {
          _items.clear();
        }
        _items.addAll(parsed);
        _nextPageKey = nextKey;
        _pageCount = pageCount;
        notifyListeners();
        return _nextPageKey;
      } else {
        if (kDebugMode) print('fetchForGroup: status=${resp.statusCode} body=${resp.body}');
      }
    } catch (e) {
      if (kDebugMode) print('fetchForGroup failed: $e');
    }
    return null;
  }

  /// Fetch documents for a group using a custom `tableReq` payload.
  /// `tableReq` should contain `scale` and `filter` keys as required by the backend.
  Future<String?> fetchForGroupWithTableReq(String grpCode,
      {Map<String, dynamic>? tableReq, String? bearerToken, String? pageKey, bool append = false}) async {
    try {
      final uri = Uri.parse('$baseUrl/api/v1/table/documents');

      final req = http.MultipartRequest('POST', uri);

      final Map<String, dynamic> effectiveTableReq = tableReq != null ? Map.from(tableReq) : {};

      // include page key inside tableReq for backends that expect it there
      if (pageKey != null && pageKey.isNotEmpty) {
        effectiveTableReq['pageKey'] = pageKey;
        effectiveTableReq['page_key'] = pageKey;
      }

      req.fields['tableReq'] = jsonEncode(effectiveTableReq);
      req.fields['grp_code'] = grpCode;
      if (pageKey != null && pageKey.isNotEmpty) req.fields['pageKey'] = pageKey;

      if (bearerToken != null && bearerToken.isNotEmpty) {
        req.headers['Authorization'] = 'Bearer $bearerToken';
      }

      if (kDebugMode) {
        try {
          print('fetchForGroupWithTableReq: POST ${uri.toString()}');
          print('fetchForGroupWithTableReq: tableReq=${req.fields['tableReq']}');
          print('fetchForGroupWithTableReq: grp_code=${req.fields['grp_code']}');
        } catch (e) {
          print('fetchForGroupWithTableReq debug failed: $e');
        }
      }

      final streamed = await req.send();
      final resp = await http.Response.fromStream(streamed);

      if (resp.statusCode >= 200 && resp.statusCode < 300) {
        final decoded = _parseDocumentsResponse(resp.body);
        final List<Flat> parsed = decoded['items'] as List<Flat>;
        final String? nextKey = decoded['pageKey'] as String?;
        final int? pageCount = decoded['pageCount'] as int?;

        if (!append) {
          _items.clear();
        }
        _items.addAll(parsed);
        _nextPageKey = nextKey;
        _pageCount = pageCount;
        notifyListeners();
        return _nextPageKey;
      } else {
        if (kDebugMode) print('fetchForGroupWithTableReq: status=${resp.statusCode} body=${resp.body}');
      }
    } catch (e) {
      if (kDebugMode) print('fetchForGroupWithTableReq failed: $e');
    }
    return null;
  }

  /// Delete a document by remote id. Returns true on success.
  Future<bool> deleteRemote(String remoteId, String bearerToken) async {
    try {
      final uri = Uri.parse('$baseUrl/api/v1/document/$remoteId');

      // Some backends expect multipart data even for DELETE; send as multipart with id field
      final req = http.MultipartRequest('DELETE', uri);
      req.fields['id'] = remoteId;
      req.headers['Accept'] = 'application/json';
      if (bearerToken.isNotEmpty) req.headers['Authorization'] = 'Bearer $bearerToken';

      if (kDebugMode) {
        try {
          print('deleteRemote: DELETE ${uri.toString()}');
          print('deleteRemote: headers=${req.headers}');
          print('deleteRemote: fields=${req.fields}');
        } catch (_) {}
      }

      final streamed = await req.send();
      final resp = await http.Response.fromStream(streamed);
      if (resp.statusCode >= 200 && resp.statusCode < 300) {
        // remove from local list
        _items.removeWhere((x) => x.remoteId != null && x.remoteId == remoteId);
        notifyListeners();
        return true;
      }
      if (kDebugMode) print('deleteRemote failed status=${resp.statusCode} body=${resp.body}');
    } catch (e) {
      if (kDebugMode) print('deleteRemote exception: $e');
    }
    return false;
  }

  /// Parse the body returned by `/api/v1/table/documents` (or similar)
  /// into a list of `Flat` instances. This is split out for unit testing.
  List<Flat> parseDocumentsBody(String bodyString) {
      final result = <Flat>[];
      try {
        final body = jsonDecode(bodyString);
        List<dynamic> rows = [];

        if (body is List) rows = body;
        else if (body is Map) {
          if (body['data'] is List) rows = body['data'];
          else if (body['rows'] is List) rows = body['rows'];
          else if (body['result'] is List) rows = body['result'];
          else if (body['data'] is Map && body['data']['rows'] is List) rows = body['data']['rows'];
          else if (body['data'] is Map && body['data']['data'] is List) rows = body['data']['data'];
          else if (body['data'] is List) rows = body['data'];
        }

        for (final item in rows) {
          try {
            if (item is! Map) continue;

            final owners = <Person>[];
            String title = '';
            Map<String, dynamic>? entitiesMap;
            String? detectedFormRowId;

            if (item['title'] != null) title = item['title'].toString();
            else if (item['name'] != null) title = item['name'].toString();
            else if (item['number'] != null) title = item['number'].toString();

            void extractFromEntities(Map entities) {
              // capture the whole entities map for later use
              if (entitiesMap == null) entitiesMap = Map<String, dynamic>.from(entities);
              for (final entry in entities.entries) {
                final k = entry.key.toString();
                final v = entry.value;
                if (k.contains('cont_name')) {
                  final name = v?.toString() ?? '';
                  final phoneKey = k.replaceFirst('cont_name', 'cont_phone');
                  final phone = entities[phoneKey]?.toString() ?? '';
                  owners.add(Person(name: name, phone: phone));
                }
                if (k == 'title' && (title.isEmpty)) {
                  title = v?.toString() ?? '';
                }
              }
            }

            if (item['main_attr'] is String) {
              try {
                final parsed = jsonDecode(item['main_attr']);
                if (parsed is List) {
                  final Map<String, String> ma = {};
                  for (final e in parsed) {
                    if (e is Map) {
                      final k = e['Key']?.toString();
                      final v = e['Value']?.toString() ?? '';
                      if (k != null) ma[k] = v;
                    }
                  }
                  if (ma.containsKey('title') && title.isEmpty) title = ma['title']!;
                  for (final k in ma.keys) {
                    if (k.contains('cont_name')) {
                      final name = ma[k] ?? '';
                      final phoneKey = k.replaceFirst('cont_name', 'cont_phone');
                      final phone = ma[phoneKey] ?? '';
                      owners.add(Person(name: name, phone: phone));
                    }
                  }
                  // merge main_attr parsed map into entitiesMap as best-effort
                  if (entitiesMap == null) {
                    entitiesMap = {};
                  }
                  entitiesMap!.addEntries(ma.entries.map((e) => MapEntry(e.key, e.value)));
                }
              } catch (_) {}
            }

            if (item['owners'] is List) {
              for (final o in (item['owners'] as List)) {
                if (o is Map) owners.add(Person(name: o['name']?.toString() ?? '', phone: o['phone']?.toString() ?? ''));
              }
            }

            if (item['entities'] is Map) extractFromEntities(Map.from(item['entities']));
            if (item['dynamicF'] is Map) {
              for (final entry in (item['dynamicF'] as Map).entries) {
                final key = entry.key?.toString();
                final v = entry.value;
                if (v is Map && v['entities'] is Map) {
                  // prefer first discovered dynamicF entry as formRowId
                  if (detectedFormRowId == null && key != null) detectedFormRowId = key;
                  extractFromEntities(Map.from(v['entities']));
                }
              }
            }

            final id = item['id']?.toString() ?? item['document_id']?.toString();
            final f = Flat(number: title, owners: owners, remoteId: id, formRowId: detectedFormRowId, formEntities: entitiesMap);
            result.add(f);
          } catch (e) {
            if (kDebugMode) print('parseDocumentsBody: item parse failed: $e');
          }
        }
      } catch (e) {
        if (kDebugMode) print('parseDocumentsBody failed: $e');
      }

      return result;
  }

  /// Parse a response body and return a map containing `items` (List<Flat>)
  /// and optional `pageKey` (String) for pagination.
  Map<String, dynamic> _parseDocumentsResponse(String bodyString) {
    final items = parseDocumentsBody(bodyString);
    String? pageKey;
    int? pageCount;
    dynamic parsedBody;
    try {
      parsedBody = jsonDecode(bodyString);
    } catch (_) {
      parsedBody = null;
    }

    try {
      final body = parsedBody;
      if (body is Map) {
        if (body['pageKey'] != null) pageKey = body['pageKey'].toString();
        else if (body['page_key'] != null) pageKey = body['page_key'].toString();
        else if (body['next'] != null && body['next']['pageKey'] != null) pageKey = body['next']['pageKey'].toString();
        else if (body['data'] is Map && body['data']['pageKey'] != null) pageKey = body['data']['pageKey'].toString();

        if (body['pageCount'] != null) pageCount = int.tryParse(body['pageCount'].toString());
        else if (body['page_count'] != null) pageCount = int.tryParse(body['page_count'].toString());
        else if (body['data'] is Map && body['data']['pageCount'] != null) pageCount = int.tryParse(body['data']['pageCount'].toString());
        else if (body['data'] is Map && body['data']['page_count'] != null) pageCount = int.tryParse(body['data']['page_count'].toString());
        else if (body['meta'] is Map && body['meta']['pageCount'] != null) pageCount = int.tryParse(body['meta']['pageCount'].toString());
        else if (body['pagination'] is Map && body['pagination']['pageCount'] != null) pageCount = int.tryParse(body['pagination']['pageCount'].toString());
      }
    } catch (_) {}

    return {'items': items, 'pageKey': pageKey, 'pageCount': pageCount};
  }

  /// Fetch a single document by UUID and convert to `Flat`.
  Future<Flat?> getDocument(String uuid, {String? bearerToken}) async {
    try {
      final uri = Uri.parse('$baseUrl/api/v1/document/$uuid');
      final headers = <String, String>{'Accept': 'application/json'};
      if (bearerToken != null && bearerToken.isNotEmpty) headers['Authorization'] = 'Bearer $bearerToken';

      final resp = await http.get(uri, headers: headers);
      if (resp.statusCode >= 200 && resp.statusCode < 300) {
        try {
          final body = jsonDecode(resp.body);
          if (body is Map && body['data'] is Map) {
            final data = body['data'] as Map;
              // try to find formFormat entries for various form keys (flat/kasa etc.)
              if (data['formFormat'] is Map) {
                final ff = data['formFormat'] as Map;

                // choose a form map key that matches known patterns, prefer target (kasa) then flat, else first entry
                String? chosenFormKey;
                for (final k in ff.keys) {
                  final kl = k.toString();
                  if (kl.contains('op-doc-target-form')) {
                    chosenFormKey = kl;
                    break;
                  }
                }
                if (chosenFormKey == null) {
                  for (final k in ff.keys) {
                    final kl = k.toString();
                    if (kl.contains('op-doc-flat-form')) {
                      chosenFormKey = kl;
                      break;
                    }
                  }
                }
                chosenFormKey ??= ff.keys.isNotEmpty ? ff.keys.first.toString() : null;

                if (chosenFormKey != null) {
                  final formMapCandidate = ff[chosenFormKey];
                  if (formMapCandidate is Map) {
                    // take first entry inside the chosen form map
                    if (formMapCandidate.isNotEmpty) {
                      final firstKey = formMapCandidate.keys.first.toString();
                      final entry = formMapCandidate[firstKey];
                      if (entry is Map && entry['entities'] is Map) {
                        final entities = Map<String, dynamic>.from(entry['entities'] as Map);
                        final owners = <Person>[];
                        String title = '';
                        if (entities['title'] != null) title = entities['title'].toString();
                        for (final eK in entities.keys) {
                          if (eK.contains('cont_name')) {
                            final name = entities[eK]?.toString() ?? '';
                            final phoneKey = eK.replaceFirst('cont_name', 'cont_phone');
                            final phone = entities[phoneKey]?.toString() ?? '';
                            owners.add(Person(name: name, phone: phone));
                          }
                        }
                        final f = Flat(number: title, owners: owners, remoteId: uuid, formRowId: firstKey, formEntities: entities);
                        return f;
                      }
                    }
                  }
                }
              }
          }
        } catch (e) {
          if (kDebugMode) print('getDocument parse failed: $e');
        }
      } else {
        if (kDebugMode) print('getDocument status=${resp.statusCode} body=${resp.body}');
      }
    } catch (e) {
      if (kDebugMode) print('getDocument failed: $e');
    }
    return null;
  }
}
