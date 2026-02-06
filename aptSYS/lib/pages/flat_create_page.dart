import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_libphonenumber/flutter_libphonenumber.dart';
import 'package:provider/provider.dart';

import '../models/flat.dart';
import '../providers/auth_provider.dart';
import '../providers/apartments_provider.dart';
import '../providers/document_provider.dart';

class FlatCreatePage extends StatefulWidget {
  final Flat? initialFlat;
  final ValueChanged<Flat>? onSaved;

  const FlatCreatePage({Key? key, this.initialFlat, this.onSaved}) : super(key: key);

  @override
  _FlatCreatePageState createState() => _FlatCreatePageState();
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

// Use `LibPhonenumberTextFormatter` from flutter_libphonenumber for region-aware formatting.

class _FlatCreatePageState extends State<FlatCreatePage> {
  final _formKey = GlobalKey<FormState>();
  final _numberCtrl = TextEditingController();
  final List<_OwnerEntry> _owners = [];

  // Fallback simple formatter when libphonenumber isn't available/initialized
  static TextInputFormatter _simplePhoneFormatter() {
    return TextInputFormatter.withFunction((oldValue, newValue) {
      final digits = newValue.text.replaceAll(RegExp(r'[^0-9]'), '');
      // basic grouping e.g. (123) 456-78-90
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
      // Try to use libphonenumber formatter if available/initialized
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
    // Prefill when editing an existing flat
    if (widget.initialFlat != null) {
      _numberCtrl.text = widget.initialFlat!.number;
      if (widget.initialFlat!.owners.isNotEmpty) {
        // If original formEntities are present, map cont_name/cont_phone keys to owner entries
        final originalEntities = widget.initialFlat!.formEntities;
        List<String> contNameKeys = [];
        if (originalEntities != null && originalEntities.isNotEmpty) {
          contNameKeys = originalEntities.keys.where((k) => k.contains('cont_name')).toList()..sort();
        }
          if (kDebugMode) print('init: originalEntities=$originalEntities contNameKeys=$contNameKeys');
        for (var i = 0; i < widget.initialFlat!.owners.length; i++) {
          final p = widget.initialFlat!.owners[i];
          final e = _OwnerEntry();
          e.nameCtrl.text = p.name;
          e.phoneCtrl.text = p.phone;
          if (i < contNameKeys.length) {
            e.nameKey = contNameKeys[i];
            e.phoneKey = contNameKeys[i].replaceFirst('cont_name', 'cont_phone');
          }
          if (kDebugMode) print('init owner entry: name=${e.nameCtrl.text} nameKey=${e.nameKey} phoneKey=${e.phoneKey}');
          _owners.add(e);
        }
      } else {
        _owners.add(_OwnerEntry());
      }
    } else {
      _owners.add(_OwnerEntry());
    }
  }

  @override
  void dispose() {
    _numberCtrl.dispose();
    for (final o in _owners) {
      o.dispose();
    }
    // controllers cleaned up above
    super.dispose();
  }

  void _addOwner() {
    setState(() => _owners.add(_OwnerEntry()));
  }

  void _removeOwner(int index) {
    setState(() {
      final removed = _owners[index];
      // record removed entity keys (if present) so the update payload can include them
      if (removed.nameKey != null && removed.nameKey!.isNotEmpty) {
        _removedEntities.add({'id': widget.initialFlat?.formRowId ?? '', 'type': 'entity', 'key': removed.nameKey!});
        if (kDebugMode) print('removed entity added: ${_removedEntities.last}');
      } else {
        // Try to discover matching key by value in the original formEntities
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
      owners: _owners
          .map((o) => Person(
                name: o.nameCtrl.text.trim(),
                phone: o.phoneCtrl.text.trim(),
                nameKey: o.nameKey,
                phoneKey: o.phoneKey,
              ))
          .toList(),
    );
    // If editing an existing flat, preserve remoteId and formRowId so updateRemote
    // can target the correct document URL and form row.
    if (widget.initialFlat != null) {
      flat.remoteId = widget.initialFlat!.remoteId;
      flat.formRowId = widget.initialFlat!.formRowId;
      // Preserve original entity keys so update replaces values instead of adding new keys
      flat.formEntities = widget.initialFlat!.formEntities == null
          ? null
          : Map<String, dynamic>.from(widget.initialFlat!.formEntities!);
      if (_removedEntities.isNotEmpty) {
        if (kDebugMode) print('attaching removedData: $_removedEntities');
        flat.removedData = List<Map<String, String>>.from(_removedEntities);
      } else {
        if (kDebugMode) print('no removedEntities to attach');
      }
    }

    if (kDebugMode) print('saving flat.removedData=${flat.removedData} formEntities=${flat.formEntities} owners=${flat.owners.map((o)=>{'name':o.name,'nameKey':(o as dynamic).nameKey}).toList()}');

    // Form submits only to API. Get token from AuthProvider and grp_code from selected apartment.
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

    // prefer explicit opKey if present, then code, then fallback to op-apt-<id>
    final grpCode = (selected.opKey != null && selected.opKey!.isNotEmpty)
        ? selected.opKey!
        : ((selected.code != null && selected.code!.isNotEmpty) ? selected.code! : 'op-apt-${selected.id}');

    final flatProv = Provider.of<DocumentProvider>(context, listen: false);
    bool success = false;
    if (widget.initialFlat != null && widget.initialFlat!.formRowId != null && widget.initialFlat!.formRowId!.isNotEmpty) {
      // update existing
      success = await flatProv.updateRemote(flat, grpCode, token, rowKey: widget.initialFlat!.formRowId);
    } else {
      success = await flatProv.createRemote(flat, grpCode, token);
    }
    if (success) {
      widget.onSaved?.call(flat);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Flat created and sent to API')));
      Navigator.of(context).pop();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to create flat on API')));
    }
  }

  // API send logic moved to DocumentProvider.createRemote

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
    final isEdit = widget.initialFlat != null;
    return Scaffold(
      appBar: AppBar(title: Text(isEdit ? 'Edit Flat' : 'Create Flat')),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Form(
          key: _formKey,
          child: ListView(
            children: [
              TextFormField(
                controller: _numberCtrl,
                decoration: const InputDecoration(labelText: 'Flat name'),
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
              ),
              const SizedBox(height: 16),
              ...List.generate(_owners.length, (i) => _buildOwnerCard(i)),
              const SizedBox(height: 12),
              // phone formatting is applied within each owner card field; ensure long values are constrained visually
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
                      child: const Text('Save Flat'),
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
