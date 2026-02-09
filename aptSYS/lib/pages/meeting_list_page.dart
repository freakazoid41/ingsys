import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:provider/provider.dart';

import '../models/flat.dart';
import '../providers/document_provider.dart';
import '../providers/apartments_provider.dart';
import '../providers/auth_provider.dart';
import 'meeting_create_page.dart';

class MeetingListPage extends StatefulWidget {
  const MeetingListPage({Key? key}) : super(key: key);

  @override
  _MeetingListPageState createState() => _MeetingListPageState();
}

class _MeetingListPageState extends State<MeetingListPage> {
  bool _loading = false;
  bool _didInit = false;
  bool _loadingMore = false;
  final ScrollController _scrollController = ScrollController();
  int _currentPage = 1;

  Map<String, String> _parseFormEntities(Map<String, dynamic>? formEntities) {
    final result = <String, String>{};
    if (formEntities == null) return result;
    
    formEntities.forEach((key, value) {
      if (value != null) {
        result[key] = value.toString();
      }
    });
    
    return result;
  }

  String _getMeetingTitle(Map<String, String> attrs) {
    // Try to find a title, or use date as fallback
    if (attrs.containsKey('meet_note') && attrs['meet_note']!.isNotEmpty) {
      return attrs['meet_note']!;
    }
    if (attrs.containsKey('meet_date') && attrs['meet_date']!.isNotEmpty) {
      return 'Meeting on ${attrs['meet_date']}';
    }
    return 'Meeting';
  }

  String _getMeetingSubtitle(Map<String, String> attrs) {
    final parts = <String>[];
    
    if (attrs.containsKey('meet_date') && attrs['meet_date']!.isNotEmpty) {
      parts.add(attrs['meet_date']!);
    }
    
    if (attrs.containsKey('meet_amount') && attrs['meet_amount']!.isNotEmpty) {
      // Find currency from the first rent entry
      String currency = '₺';
      final currencyKeys = attrs.keys.where((k) => k.startsWith('currency**meetrentsgroup**')).toList();
      if (currencyKeys.isNotEmpty) {
        final firstCurrency = attrs[currencyKeys.first];
        if (firstCurrency == 'USD') currency = '\$';
        else if (firstCurrency == 'EUR') currency = '€';
      }
      parts.add('Amount: ${attrs['meet_amount']} $currency');
    }
    
    // Count participants
    final participantCount = attrs.keys.where((k) => k.contains('cont_name**meetcontgroup')).length;
    if (participantCount > 0) {
      parts.add('$participantCount participants');
    }
    
    return parts.join(' • ');
  }
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_didInit) {
      _didInit = true;
      _loadFlats();
      _scrollController.addListener(() {
        if (_scrollController.position.atEdge) {
          final isBottom = _scrollController.position.pixels != 0;
          if (isBottom) _loadMoreFlats();
        }
      });
    }
  }

  @override
  void dispose() {
    _scrollController.removeListener(() {});
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _loadFlats() async {
    final apartmentsProv = Provider.of<ApartmentsProvider>(context, listen: false);
    final flatProv = Provider.of<DocumentProvider>(context, listen: false);
    final auth = Provider.of<AuthProvider>(context, listen: false);

    final selected = apartmentsProv.selectedApartment;
    final grp = selected?.opKey ?? selected?.code;
    if (grp == null || grp.isEmpty) return;

    setState(() => _loading = true);
    try {
      await flatProv.fetchForGroupWithTableReq(grp, tableReq: {
        'scale': {'limit': 10, 'page': _currentPage},
        'filter': [
          {'key': 'form-type', 'type': '=', 'value': 'op-doc-meeting-form'},
          {'key': 'type', 'type': '=', 'value': 'op-doc-meeting'}
        ]
      }, bearerToken: auth.token);
    } catch (e) {
      if (kDebugMode) print('loadFlats error: $e');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadMoreFlats() async {
    if (_loadingMore || _loading) return;
    final apartmentsProv = Provider.of<ApartmentsProvider>(context, listen: false);
    final flatProv = Provider.of<DocumentProvider>(context, listen: false);
    final auth = Provider.of<AuthProvider>(context, listen: false);

    final selected = apartmentsProv.selectedApartment;
    final grp = selected?.opKey ?? selected?.code;
    if (grp == null || grp.isEmpty) return;

    final nextKey = flatProv.nextPageKey;
    final pageCount = flatProv.pageCount;
    // If numeric pagination reached the last page and there's no nextKey, stop
    if ((pageCount != null) && (_currentPage >= pageCount) && (nextKey == null || nextKey.isEmpty)) {
      return;
    }

    setState(() => _loadingMore = true);
    try {
      _currentPage++;
      await flatProv.fetchForGroupWithTableReq(grp, tableReq: {
        'scale': {'limit': 10, 'page': _currentPage, 'pageKey': nextKey},
        'filter': [
          {'key': 'form-type', 'type': '=', 'value': 'op-doc-meeting-form'},
          {'key': 'type', 'type': '=', 'value': 'op-doc-meeting'}
        ]
      }, bearerToken: auth.token, append: true);
    } catch (e) {
      if (kDebugMode) print('loadMoreFlats error: $e');
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _maybeFillViewport() async {
    if (!mounted) return;
    final flatProv = Provider.of<DocumentProvider>(context, listen: false);
    final apartmentsProv = Provider.of<ApartmentsProvider>(context, listen: false);
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final selected = apartmentsProv.selectedApartment;
    final grp = selected?.opKey ?? selected?.code;
    if (grp == null || grp.isEmpty) return;

    // Loop until the list becomes scrollable, or no more items are returned
    while (mounted && _scrollController.hasClients && _scrollController.position.maxScrollExtent <= 0) {
      final prevCount = flatProv.items.length;
      final nextKey = flatProv.nextPageKey;
      final pageCount = flatProv.pageCount;
      if (nextKey != null && nextKey.isNotEmpty) {
        await flatProv.fetchForGroupWithTableReq(grp, tableReq: {
          'scale': {'limit': 10, 'pageKey': nextKey},
          'filter': [
            {'key': 'form-type', 'type': '=', 'value': 'op-doc-meeting-form'},
            {'key': 'type', 'type': '=', 'value': 'op-doc-meeting'}
          ]
        }, bearerToken: auth.token, append: true);
        if (flatProv.items.length == prevCount) break; // No new items
      } else if ((pageCount == null) || (_currentPage < pageCount)) {
        _currentPage++;
        await flatProv.fetchForGroupWithTableReq(grp, tableReq: {
          'scale': {'limit': 10, 'page': _currentPage},
          'filter': [
            {'key': 'form-type', 'type': '=', 'value': 'op-doc-meeting-form'},
            {'key': 'type', 'type': '=', 'value': 'op-doc-meeting'}
          ]
        }, bearerToken: auth.token, append: true);
        if (flatProv.items.length == prevCount) break; // No new items
      } else {
        break; // No more pages
      }
    }
  }

  void _editFlat(Flat f) {
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final flatProv = Provider.of<DocumentProvider>(context, listen: false);

    if (f.remoteId != null && f.remoteId!.isNotEmpty) {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (_) => const Center(child: CircularProgressIndicator()),
      );
      flatProv.getDocument(f.remoteId!, bearerToken: auth.token).then((remoteFlat) {
        Navigator.of(context).pop();
        if (remoteFlat != null) {
          Navigator.of(context)
              .push(MaterialPageRoute(builder: (_) => MeetingCreatePage(initialFlat: remoteFlat, onSaved: (_) => _loadFlats())))
              .then((_) => _maybeFillViewport());
        } else {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to load meeting details')));
        }
      }).catchError((e) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Error loading meeting details')));
      });
    } else {
      Navigator.of(context)
          .push(MaterialPageRoute(builder: (_) => MeetingCreatePage(initialFlat: f, onSaved: (_) => _loadFlats())))
          .then((_) => _maybeFillViewport());
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Toplantı Listesi'),
        actions: [
          IconButton(
            icon: const Icon(Icons.add),
            onPressed: () {
              Navigator.of(context)
                  .push(MaterialPageRoute(builder: (_) => MeetingCreatePage(onSaved: (_) => _loadFlats())))
                  .then((_) => _maybeFillViewport());
            },
          ),
        ],
      ),
      body: Consumer<DocumentProvider>(
        builder: (context, fp, _) {
          final items = fp.items;
          return Stack(
            children: [
              RefreshIndicator(
                onRefresh: _loadFlats,
                child: items.isEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const [Center(child: Padding(padding: EdgeInsets.all(24.0), child: Text('No meetings yet')))],
                      )
                    : ListView.builder(
                        controller: _scrollController,
                        physics: const AlwaysScrollableScrollPhysics(),
                        itemCount: items.length + (_loadingMore ? 1 : 0),
                        itemBuilder: (context, index) {
                          if (index == items.length) {
                            return const Center(child: Padding(padding: EdgeInsets.all(16.0), child: CircularProgressIndicator()));
                          }
                          final f = items[index];
                          final attrs = _parseFormEntities(f.formEntities);
                          return Card(
                            margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            child: ListTile(
                              title: Text(_getMeetingTitle(attrs)),
                              subtitle: Text(_getMeetingSubtitle(attrs)),
                              trailing: PopupMenuButton<String>(
                                onSelected: (value) {
                                  if (value == 'edit') {
                                    _editFlat(f);
                                  } else if (value == 'delete') {
                                    showDialog(
                                      context: context,
                                      builder: (context) => AlertDialog(
                                        title: const Text('Delete Meeting'),
                                        content: const Text('Are you sure you want to delete this meeting?'),
                                        actions: [
                                          TextButton(
                                            child: const Text('Cancel'),
                                            onPressed: () => Navigator.of(context).pop(),
                                          ),
                                          TextButton(
                                            child: const Text('Delete'),
                                            onPressed: () async {
                                              Navigator.of(context).pop();
                                              final auth = Provider.of<AuthProvider>(context, listen: false);
                                              final flatProv = Provider.of<DocumentProvider>(context, listen: false);
                                              if (f.remoteId != null && f.remoteId!.isNotEmpty) {
                                                final ok = await flatProv.deleteRemote(f.remoteId!, auth.token ?? '');
                                                if (ok) {
                                                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Meeting deleted')));
                                                } else {
                                                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to delete meeting')));
                                                }
                                              } else {
                                                // local-only item
                                                final idx = flatProv.items.indexWhere((x) => x.number == f.number && x.formRowId == f.formRowId);
                                                if (idx >= 0) flatProv.removeAt(idx);
                                              }
                                            },
                                          ),
                                        ],
                                      ),
                                    );
                                  }
                                },
                                itemBuilder: (context) => [
                                  const PopupMenuItem(value: 'edit', child: Text('Edit')),
                                  const PopupMenuItem(value: 'delete', child: Text('Delete')),
                                ],
                              ),
                              onTap: () => _editFlat(f),
                            ),
                          );
                        },
                      ),
              ),
              if (_loading)
                const Positioned.fill(
                  child: Center(child: CircularProgressIndicator()),
                ),
            ],
          );
        },
      ),
    );
  }
}