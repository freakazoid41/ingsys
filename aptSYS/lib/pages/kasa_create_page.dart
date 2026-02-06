import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_libphonenumber/flutter_libphonenumber.dart';
import 'package:provider/provider.dart';

import '../models/flat.dart';
import '../providers/auth_provider.dart';
import '../providers/apartments_provider.dart';
import '../providers/document_provider.dart';

class KasaCreatePage extends StatefulWidget {
  final Flat? initialFlat;
  final ValueChanged<Flat>? onSaved;

  const KasaCreatePage({Key? key, this.initialFlat, this.onSaved}) : super(key: key);

  @override
  _KasaCreatePageState createState() => _KasaCreatePageState();
}

class _OwnerEntry {
  final TextEditingController nameCtrl = TextEditingController();
  final TextEditingController phoneCtrl = TextEditingController();
  String? nameKey;
  String? phoneKey;

  void dispose() {
    nameCtrl.dispose();
    phoneCtrl.dispose();
  }
}

class _KasaCreatePageState extends State<KasaCreatePage> {
  final _formKey = GlobalKey<FormState>();
  final _numberCtrl = TextEditingController();
  final _bankCtrl = TextEditingController();
  final _ibanCtrl = TextEditingController();
  String _currency = 'TRY';
  final _noteCtrl = TextEditingController();
  // owners not used for kasa
  final List<_OwnerEntry> _owners = [];

  static TextInputFormatter _simplePhoneFormatter() {
    return TextInputFormatter.withFunction((oldValue, newValue) {
      final digits = newValue.text.replaceAll(RegExp(r'[^0-9]'), '');
      String formatted = '';
      int len = digits.length;
      int used = 0;

      if (len > 0) {
        formatted += '(';
        final take = (len >= 3) ? 3 : len;
        formatted += digits.substring(0, take);
        used += take;
        if (used == 3) formatted += ') ';
      }
      if (len > used) {
        final take = ((len - used) >= 3) ? 3 : (len - used);
        formatted += digits.substring(used, used + take);
        used += take;
        if (take == 3) formatted += '-';
      }
      if (len > used) {
        final take = ((len - used) >= 2) ? 2 : (len - used);
        formatted += digits.substring(used, used + take);
        used += take;
        if (take == 2) formatted += '-';
      }
      if (len > used) formatted += digits.substring(used);

      return TextEditingValue(text: formatted, selection: TextSelection.collapsed(offset: formatted.length));
    });
  }

  List<TextInputFormatter> _phoneInputFormatters() {
    try {
      final iso = Localizations.localeOf(context).countryCode ?? 'US';
      final countryData = CountryManager().countries.firstWhere(
        (c) => c.countryCode.toUpperCase() == iso.toUpperCase(),
        orElse: () => CountryWithPhoneCode.us(),
      );
      return [LibPhonenumberTextFormatter(country: countryData)];
    } catch (e) {
      return [FilteringTextInputFormatter.digitsOnly, _simplePhoneFormatter()];
    }
  }

  @override
  void initState() {
    super.initState();
    if (widget.initialFlat != null) {
      _numberCtrl.text = widget.initialFlat!.number;
      final entities = widget.initialFlat!.formEntities ?? {};
      try {
        if (entities['bank'] != null) _bankCtrl.text = entities['bank'].toString();
        if (entities['iban'] != null) _ibanCtrl.text = entities['iban'].toString();
        if (entities['currency'] != null) _currency = entities['currency'].toString();
        if (entities['target_note'] != null) _noteCtrl.text = entities['target_note'].toString();
        if (entities['targetNote'] != null) _noteCtrl.text = entities['targetNote'].toString();
        if (entities['note'] != null && _noteCtrl.text.isEmpty) _noteCtrl.text = entities['note'].toString();
      } catch (_) {}

      // If the initial flat lacks entities (or key fields), attempt to fetch fresh remote document
      final hasAnyEntity = (entities.isNotEmpty) && (entities['bank'] != null || entities['iban'] != null || entities['title'] != null || entities['target_note'] != null);
      if (!hasAnyEntity && widget.initialFlat!.remoteId != null && widget.initialFlat!.remoteId!.isNotEmpty) {
        Future.microtask(() async {
          try {
            final auth = Provider.of<AuthProvider>(context, listen: false);
            final flatProv = Provider.of<DocumentProvider>(context, listen: false);
            final remote = await flatProv.getDocument(widget.initialFlat!.remoteId!, bearerToken: auth.token ?? '');
            if (remote != null && remote.formEntities != null && remote.formEntities!.isNotEmpty) {
              _applyEntities(remote.formEntities!);
            }
          } catch (_) {}
        });
      }
    }
  }

  void _applyEntities(Map<String, dynamic> entities) {
    try {
      if (entities['title'] != null && (entities['title'].toString().isNotEmpty)) {
        setState(() => _numberCtrl.text = entities['title'].toString());
      }
      if (entities['bank'] != null) setState(() => _bankCtrl.text = entities['bank'].toString());
      if (entities['iban'] != null) setState(() => _ibanCtrl.text = entities['iban'].toString());
      if (entities['currency'] != null) setState(() => _currency = entities['currency'].toString());
      if (entities['target_note'] != null) setState(() => _noteCtrl.text = entities['target_note'].toString());
      if (entities['targetNote'] != null && _noteCtrl.text.isEmpty) setState(() => _noteCtrl.text = entities['targetNote'].toString());
      if (entities['note'] != null && _noteCtrl.text.isEmpty) setState(() => _noteCtrl.text = entities['note'].toString());
    } catch (_) {}
  }

  @override
  void dispose() {
    _numberCtrl.dispose();
    for (final o in _owners) {
      o.dispose();
    }
    super.dispose();
  }

  void _addOwner() {
    setState(() => _owners.add(_OwnerEntry()));
  }

  void _removeOwner(int index) {
    setState(() {
      final removed = _owners[index];
      if (removed.nameKey != null && removed.nameKey!.isNotEmpty) {
        _removedEntities.add({'id': widget.initialFlat?.formRowId ?? '', 'type': 'entity', 'key': removed.nameKey!});
        if (kDebugMode) print('removed entity added: ${_removedEntities.last}');
      } else {
        final orig = widget.initialFlat?.formEntities;
        if (orig != null) {
          for (final k in orig.keys) {
            if (k.contains('cont_name')) {
              final v = orig[k]?.toString() ?? '';
              if (v == removed.nameCtrl.text.trim()) {
                _removedEntities.add({'id': widget.initialFlat?.formRowId ?? '', 'type': 'entity', 'key': k});
                if (kDebugMode) print('removed entity discovered by value: ${_removedEntities.last}');
                break;
              }
            }
          }
        }
      }
      if (removed.phoneKey != null && removed.phoneKey!.isNotEmpty) {
        _removedEntities.add({'id': widget.initialFlat?.formRowId ?? '', 'type': 'entity', 'key': removed.phoneKey!});
        if (kDebugMode) print('removed entity added: ${_removedEntities.last}');
      } else {
        final orig = widget.initialFlat?.formEntities;
        if (orig != null) {
          for (final k in orig.keys) {
            if (k.contains('cont_phone')) {
              final v = orig[k]?.toString() ?? '';
              if (v == removed.phoneCtrl.text.trim()) {
                _removedEntities.add({'id': widget.initialFlat?.formRowId ?? '', 'type': 'entity', 'key': k});
                if (kDebugMode) print('removed entity discovered by value: ${_removedEntities.last}');
                break;
              }
            }
          }
        }
      }
      if (kDebugMode) print('current removedEntities: $_removedEntities');
      removed.dispose();
      _owners.removeAt(index);
    });
  }

  final List<Map<String, String>> _removedEntities = [];

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    final flat = Flat(
      number: _numberCtrl.text.trim(),
      owners: [],
    );
    // build formEntities for kasa
    final Map<String, dynamic> entities = {};
    entities['title'] = _numberCtrl.text.trim();
    if (_bankCtrl.text.trim().isNotEmpty) entities['bank'] = _bankCtrl.text.trim();
    if (_ibanCtrl.text.trim().isNotEmpty) entities['iban'] = _ibanCtrl.text.trim();
    if (_currency.isNotEmpty) entities['currency'] = _currency;
    if (_noteCtrl.text.trim().isNotEmpty) entities['target_note'] = _noteCtrl.text.trim();
    flat.formEntities = entities;
    if (widget.initialFlat != null) {
      flat.remoteId = widget.initialFlat!.remoteId;
      flat.formRowId = widget.initialFlat!.formRowId;
      // preserve removedData if any (not really used for kasa fields but keep for compatibility)
      if (_removedEntities.isNotEmpty) flat.removedData = List<Map<String, String>>.from(_removedEntities);
    }

    if (kDebugMode) print('saving flat.removedData=${flat.removedData} formEntities=${flat.formEntities} owners=${flat.owners.map((o)=>{'name':o.name,'nameKey':(o as dynamic).nameKey}).toList()}');

    final auth = Provider.of<AuthProvider>(context, listen: false);
    final token = auth.token ?? '';
    if (token.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('No API token available. Please login.')));
      return;
    }

    final apartmentsProvider = Provider.of<ApartmentsProvider>(context, listen: false);
    final selected = apartmentsProvider.selectedApartment;
    if (selected == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('No apartment selected. Please select an apartment first.')));
      return;
    }

    final grpCode = (selected.opKey != null && selected.opKey!.isNotEmpty)
        ? selected.opKey!
        : ((selected.code != null && selected.code!.isNotEmpty) ? selected.code! : 'op-apt-${selected.id}');

    final flatProv = Provider.of<DocumentProvider>(context, listen: false);
    bool success = false;
    if (widget.initialFlat != null && widget.initialFlat!.formRowId != null && widget.initialFlat!.formRowId!.isNotEmpty) {
      success = await flatProv.updateRemote(flat, grpCode, token, rowKey: widget.initialFlat!.formRowId, formTag: 'op-doc-target-form', typeKey: 'op-doc-target');
    } else {
      success = await flatProv.createRemote(flat, grpCode, token, formTag: 'op-doc-target-form', typeKey: 'op-doc-target');
    }
    if (success) {
      widget.onSaved?.call(flat);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Kasa created and sent to API')));
      Navigator.of(context).pop();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to create kasa on API')));
    }
  }

  Widget _buildOwnerCard(int index) {
    final owner = _owners[index];
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 8.0),
      child: Padding(
        padding: const EdgeInsets.all(12.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Owner ${index + 1}', style: const TextStyle(fontWeight: FontWeight.w600)),
                IconButton(
                  icon: const Icon(Icons.delete_outline),
                  onPressed: _owners.length > 1 ? () => _removeOwner(index) : null,
                ),
              ],
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: owner.nameCtrl,
              decoration: const InputDecoration(labelText: 'Name'),
              validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: owner.phoneCtrl,
              decoration: InputDecoration(
                labelText: 'Phone',
                suffixIcon: IconButton(
                  icon: const Icon(Icons.phone),
                  onPressed: () {},
                ),
              ),
              keyboardType: TextInputType.phone,
              inputFormatters: _phoneInputFormatters(),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final apartmentsProvider = Provider.of<ApartmentsProvider>(context);
    final isEdit = widget.initialFlat != null;
    return Scaffold(
      appBar: AppBar(title: Text(isEdit ? 'Edit Kasa' : 'Create Kasa')),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Form(
          key: _formKey,
          child: ListView(
            children: [
              TextFormField(
                controller: _numberCtrl,
                decoration: const InputDecoration(labelText: 'Başlık'),
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _bankCtrl,
                      decoration: const InputDecoration(labelText: 'Banka'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      controller: _ibanCtrl,
                      decoration: const InputDecoration(labelText: 'IBAN'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  SizedBox(
                    width: 160,
                    child: DropdownButtonFormField<String>(
                      initialValue: _currency,
                      decoration: const InputDecoration(labelText: 'Kur Tipi'),
                      items: const [
                        DropdownMenuItem(value: 'TRY', child: Text('TRY ₺')),
                        DropdownMenuItem(value: 'EUR', child: Text('EUR €')),
                        DropdownMenuItem(value: 'USD', child: Text('USD \$')),
                      ],
                      onChanged: (v) {
                        if (v != null) setState(() => _currency = v);
                      },
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _noteCtrl,
                decoration: const InputDecoration(labelText: 'Kasa Açıklama'),
                maxLines: 4,
              ),
              const SizedBox(height: 16),
              ...List.generate(_owners.length, (i) => _buildOwnerCard(i)),
              const SizedBox(height: 12),
              Row(
                children: [
                  ElevatedButton.icon(
                    onPressed: _addOwner,
                    icon: const Icon(Icons.add),
                    label: const Text('Add Person'),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: OutlinedButton(
                      onPressed: _save,
                      child: const Text('Save Kasa'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
