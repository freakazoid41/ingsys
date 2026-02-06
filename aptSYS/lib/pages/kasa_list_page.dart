
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/flat_provider.dart';
import '../providers/apartments_provider.dart';
import '../providers/auth_provider.dart';
import '../providers/global_loading_provider.dart';
import 'kasa_create_page.dart';

class KasaListPage extends StatefulWidget {
  const KasaListPage({Key? key}) : super(key: key);

  @override
  _KasaListPageState createState() => _KasaListPageState();
}

class _KasaListPageState extends State<KasaListPage> {
  bool _loading = false;
  bool _didInit = false;
  bool _loadingMore = false;
  final ScrollController _scrollController = ScrollController();
  int _currentPage = 1;
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
    final flatProv = Provider.of<FlatProvider>(context, listen: false);
    final auth = Provider.of<AuthProvider>(context, listen: false);

    final selected = apartmentsProv.selectedApartment;
    final grp = selected?.opKey ?? selected?.code;
    if (grp == null || grp.isEmpty) return;

    setState(() => _loading = true);
    // show global loader
    try {
      context.read<GlobalLoadingProvider>().showLoading();
    } catch (_) {}
    _currentPage = 1;
    final tableReq = {
      'scale': {'limit': 10, 'page': _currentPage},
      'filter': [
        {'key': 'form-type', 'type': '=', 'value': 'op-doc-target-form'},
        {'key': 'type', 'type': '=', 'value': 'op-doc-target'}
      ]
    };
    await flatProv.fetchForGroupWithTableReq(grp, tableReq: tableReq, bearerToken: auth.token, append: false);
    setState(() => _loading = false);
    try {
      await Future.delayed(const Duration(milliseconds: 500));
      context.read<GlobalLoadingProvider>().hideLoading();
    } catch (_) {}

    // If the list content doesn't fill the viewport, try loading additional pages
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _maybeFillViewport();
    });
  }

  Future<void> _loadMoreFlats() async {
    if (_loadingMore || _loading) return;
    final apartmentsProv = Provider.of<ApartmentsProvider>(context, listen: false);
    final flatProv = Provider.of<FlatProvider>(context, listen: false);
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
    final prevCount = flatProv.items.length;
    if (nextKey != null && nextKey.isNotEmpty) {
      final tableReqNext = {
        'scale': {'limit': 10, 'pageKey': nextKey},
        'filter': [
          {'key': 'form-type', 'type': '=', 'value': 'op-doc-target-form'},
          {'key': 'type', 'type': '=', 'value': 'op-doc-target'}
        ],
        'pageKey': nextKey
      };
      await flatProv.fetchForGroupWithTableReq(grp, tableReq: tableReqNext, bearerToken: auth.token, pageKey: nextKey, append: true);
    } else {
      // use page-based pagination when no pageKey is provided
      // ensure we don't exceed pageCount if provided
      if (pageCount != null && _currentPage >= pageCount) {
        setState(() => _loadingMore = false);
        return;
      }
      _currentPage += 1;
      final tableReqMore = {
        'scale': {'limit': 10, 'page': _currentPage},
        'filter': [
          {'key': 'form-type', 'type': '=', 'value': 'op-doc-target-form'},
          {'key': 'type', 'type': '=', 'value': 'op-doc-target'}
        ]
      };
      await flatProv.fetchForGroupWithTableReq(grp, tableReq: tableReqMore, bearerToken: auth.token, pageKey: null, append: true);
    }
    final newCount = flatProv.items.length;
    setState(() => _loadingMore = false);
    // If we loaded items and still the list is not scrollable, attempt to load more
    if (newCount > prevCount) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _maybeFillViewport());
    }
  }

  Future<void> _maybeFillViewport() async {
    if (!mounted) return;
    final flatProv = Provider.of<FlatProvider>(context, listen: false);
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
        final tableReqNext = {
          'scale': {'limit': 10, 'pageKey': nextKey},
          'filter': [
            {'key': 'form-type', 'type': '=', 'value': 'op-doc-target-form'},
            {'key': 'type', 'type': '=', 'value': 'op-doc-target'}
          ],
          'pageKey': nextKey
        };
        await flatProv.fetchForGroupWithTableReq(grp, tableReq: tableReqNext, bearerToken: auth.token, pageKey: nextKey, append: true);
      } else {
        // stop if we've reached the known pageCount
        if (pageCount != null && _currentPage >= pageCount) break;
        _currentPage += 1;
        final tableReqMore = {
          'scale': {'limit': 10, 'page': _currentPage},
          'filter': [
            {'key': 'form-type', 'type': '=', 'value': 'op-doc-target-form'},
            {'key': 'type', 'type': '=', 'value': 'op-doc-target'}
          ]
        };
        await flatProv.fetchForGroupWithTableReq(grp, tableReq: tableReqMore, bearerToken: auth.token, pageKey: null, append: true);
      }
      final newCount = flatProv.items.length;
      if (newCount <= prevCount) break; // no more data
      // allow frame to update and recalc scroll metrics
      await Future.delayed(const Duration(milliseconds: 50));
    }
  }

  

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Consumer<FlatProvider>(
        builder: (context, fp, _) {
          final items = fp.items;
          return Stack(
            children: [
              RefreshIndicator(
                onRefresh: _loadFlats,
                child: items.isEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const [Center(child: Padding(padding: EdgeInsets.all(24.0), child: Text('No flats yet')))],
                      )
                    : ListView.builder(
                        controller: _scrollController,
                        itemCount: items.length + (_loadingMore ? 1 : 0),
                        itemBuilder: (context, index) {
                          if (index >= items.length) {
                            return const Padding(
                              padding: EdgeInsets.symmetric(vertical: 16.0),
                              child: Center(child: CircularProgressIndicator()),
                            );
                          }
                          final f = items[index];
                          return Card(
                            margin: const EdgeInsets.symmetric(horizontal: 8.0, vertical: 4.0),
                            child: ListTile(
                              title: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(f.number),
                                  const SizedBox(height: 4),
                                  Builder(builder: (_) {
                                    final be = f.formEntities ?? {};
                                    final bank = (be['bank'] ?? '').toString();
                                    final iban = (be['iban'] ?? '').toString();
                                    final parts = <String>[];
                                    if (bank.isNotEmpty) parts.add(bank);
                                    if (iban.isNotEmpty) parts.add(iban);
                                    if (parts.isEmpty) return const SizedBox.shrink();
                                    return Text(parts.join(' — '), style: const TextStyle(fontSize: 12, color: Colors.grey));
                                  })
                                ],
                              ),
                              trailing: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  IconButton(
                                    icon: const Icon(Icons.edit),
                                    onPressed: () {
                                      final auth = Provider.of<AuthProvider>(context, listen: false);
                                      final flatProv = Provider.of<FlatProvider>(context, listen: false);

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
                                                .push(MaterialPageRoute(builder: (_) => KasaCreatePage(initialFlat: remoteFlat, onSaved: (_) => _loadFlats())))
                                                .then((_) => _loadFlats());
                                          } else {
                                            ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to load remote kasa for editing')));
                                          }
                                        });
                                      } else {
                                        Navigator.of(context).push(MaterialPageRoute(builder: (_) => KasaCreatePage(initialFlat: f))).then((_) => _loadFlats());
                                      }
                                    },
                                  ),
                                  IconButton(
                                    icon: const Icon(Icons.delete_outline),
                                    onPressed: () async {
                                      final confirmed = await showDialog<bool>(
                                        context: context,
                                        builder: (ctx) => AlertDialog(
                                          title: const Text('Delete flat'),
                                          content: const Text('Are you sure you want to delete this flat?'),
                                          actions: [
                                            TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
                                            TextButton(onPressed: () async {
                                                setState(() => _loading = true);
                                                context.read<GlobalLoadingProvider>().showLoading();
                                                Navigator.of(ctx).pop(true);
                                                await Future.delayed(const Duration(milliseconds: 500));
                                                context.read<GlobalLoadingProvider>().hideLoading();
                                              }
                                            , child: const Text('Delete')),
                                          ],
                                        ),
                                      );
                                      if (confirmed != true) return;
                                      final auth = Provider.of<AuthProvider>(context, listen: false);
                                      final flatProv = Provider.of<FlatProvider>(context, listen: false);
                                      if (f.remoteId != null && f.remoteId!.isNotEmpty) {
                                        final ok = await flatProv.deleteRemote(f.remoteId!, auth.token ?? '');
                                        if (ok) {
                                          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Flat deleted')));
                                        } else {
                                          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to delete flat')));
                                        }
                                      } else {
                                        // local-only item
                                        final idx = flatProv.items.indexWhere((x) => x.number == f.number && x.formRowId == f.formRowId);
                                        if (idx >= 0) flatProv.removeAt(idx);
                                        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Flat removed locally')));
                                      }
                                    },
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
              ),
              // Global loader now handled by `GlobalLoadingProvider` in `main.dart`.
            ],
          );
        },
      ),
      floatingActionButton: FloatingActionButton(
        child: const Icon(Icons.add),
        onPressed: () {
          Navigator.of(context)
              .push(MaterialPageRoute(builder: (_) => KasaCreatePage(onSaved: (_) => _loadFlats())))
              .then((_) => _loadFlats());
        },
      ),
      appBar: AppBar(
        title: const Text('Kasa Listesi'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadFlats,
          )
        ],
      ),
    );
  }
}
