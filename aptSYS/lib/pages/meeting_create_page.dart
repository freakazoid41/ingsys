import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../models/flat.dart';
import '../providers/auth_provider.dart';
import '../providers/apartments_provider.dart';
import '../providers/document_provider.dart';

class MeetingCreatePage extends StatefulWidget {
  final Flat? initialFlat;
  final ValueChanged<Flat>? onSaved;

  const MeetingCreatePage({Key? key, this.initialFlat, this.onSaved}) : super(key: key);

  @override
  _MeetingCreatePageState createState() => _MeetingCreatePageState();
}

class _ParticipantEntry {
  final TextEditingController nameCtrl = TextEditingController();
  final TextEditingController phoneCtrl = TextEditingController();
  final TextEditingController emailCtrl = TextEditingController();
  String? gid;

  void dispose() {
    nameCtrl.dispose();
    phoneCtrl.dispose();
    emailCtrl.dispose();
  }
}

class _RentEntry {
  final TextEditingController titleCtrl = TextEditingController();
  final TextEditingController amountCtrl = TextEditingController();
  String currency = 'TRY';
  String? gid;
  final FocusNode amountFocus = FocusNode();

  void dispose() {
    titleCtrl.dispose();
    amountCtrl.dispose();
    amountFocus.dispose();
  }
}

class _MeetingCreatePageState extends State<MeetingCreatePage> {
  final _formKey = GlobalKey<FormState>();
  final _titleCtrl = TextEditingController();
  final _dateCtrl = TextEditingController();
  final _amountCtrl = TextEditingController();
  late final FocusNode _amountFocus;
  final _supervisorCtrl = TextEditingController();
  final _subSupervisorCtrl = TextEditingController();
  final List<_ParticipantEntry> _participants = [];
  final List<_RentEntry> _rents = [];
  final List<String> _removedKeys = [];

  @override
  void initState() {
    super.initState();
    // format total amount on focus change (show raw when focused, formatted on blur)
    _amountFocus = FocusNode();
    _amountFocus.addListener(() {
      if (_amountFocus.hasFocus) {
        _amountCtrl.text = _normalizeAmountString(_amountCtrl.text);
        _amountCtrl.selection = TextSelection.fromPosition(TextPosition(offset: _amountCtrl.text.length));
      } else {
        final raw = _normalizeAmountString(_amountCtrl.text);
        if (raw.isNotEmpty) _amountCtrl.text = _formatAmountForDisplay(raw);
      }
    });
    if (widget.initialFlat != null) {
      _loadFromFlat(widget.initialFlat!);
    }
  }

  void _loadFromFlat(Flat f) {
    if (f.formEntities != null) {
      _applyEntities(f.formEntities!);
    }
  }

  void _applyEntities(Map<String, dynamic> entities) {
    // Handle main_attr if present
    if (entities.containsKey('main_attr') && entities['main_attr'] is String) {
      try {
        final mainAttr = jsonDecode(entities['main_attr']);
        if (mainAttr is List) {
          final Map<String, String> attrs = {};
          for (final item in mainAttr) {
            if (item is Map && item['Key'] != null) {
              attrs[item['Key']] = item['Value']?.toString() ?? '';
            }
          }
          _applyAttrs(attrs);
        }
      } catch (e) {
        // Fall back to regular entities
        _applyAttrs(Map<String, String>.from(entities.map((k, v) => MapEntry(k, v?.toString() ?? ''))));
      }
    } else {
      _applyAttrs(Map<String, String>.from(entities.map((k, v) => MapEntry(k, v?.toString() ?? ''))));
    }
  }

  void _applyAttrs(Map<String, String> attrs) {
    if (attrs.containsKey('meet_note')) _titleCtrl.text = attrs['meet_note']!;
    if (attrs.containsKey('meet_date')) _dateCtrl.text = attrs['meet_date']!;
    if (attrs.containsKey('meet_amount')) {
      _amountCtrl.text = _normalizeAmountString(attrs['meet_amount']!);
      // show formatted display
      if (_amountCtrl.text.isNotEmpty) _amountCtrl.text = _formatAmountForDisplay(_amountCtrl.text);
    }
    if (attrs.containsKey('meet_active_supervisor')) _supervisorCtrl.text = attrs['meet_active_supervisor']!;
    if (attrs.containsKey('meet_active_supervisor_sub')) _subSupervisorCtrl.text = attrs['meet_active_supervisor_sub']!;

    // Load participants
    final participantKeys = attrs.keys.where((k) => k.contains('cont_name**meetcontgroup')).toList();
    for (final key in participantKeys) {
      final name = attrs[key] ?? '';
      final gid = key.replaceFirst('cont_name**meetcontgroup**', '');
      final phoneKey = 'cont_phone**meetcontgroup**$gid';
      final emailKey = 'cont_mail**meetcontgroup**$gid';
      final phone = attrs[phoneKey] ?? '';
      final email = attrs[emailKey] ?? '';

      final participant = _ParticipantEntry();
      participant.gid = gid;
      participant.nameCtrl.text = name;
      participant.phoneCtrl.text = phone;
      participant.emailCtrl.text = email;
      _participants.add(participant);
    }

    // Load rents
    final rentKeys = attrs.keys.where((k) => k.contains('meet_rent_title**meetrentsgroup')).toList();
    for (final key in rentKeys) {
      final title = attrs[key] ?? '';
      final gid = key.replaceFirst('meet_rent_title**meetrentsgroup**', '');
      final amountKey = 'meet_rent**meetrentsgroup**$gid';
      final currencyKey = 'currency**meetrentsgroup**$gid';
      final amount = attrs[amountKey] ?? '';
      final currency = attrs[currencyKey] ?? 'TRY';

      final rent = _RentEntry();
      rent.gid = gid;
      rent.titleCtrl.text = title;
      rent.amountCtrl.text = _normalizeAmountString(amount);
      if (rent.amountCtrl.text.isNotEmpty) rent.amountCtrl.text = _formatAmountForDisplay(rent.amountCtrl.text);
      // attach focus listener to toggle raw/formatted on focus change
      rent.amountFocus.addListener(() {
        if (rent.amountFocus.hasFocus) {
          rent.amountCtrl.text = _normalizeAmountString(rent.amountCtrl.text);
          rent.amountCtrl.selection = TextSelection.fromPosition(TextPosition(offset: rent.amountCtrl.text.length));
        } else {
          final raw = _normalizeAmountString(rent.amountCtrl.text);
          if (raw.isNotEmpty) rent.amountCtrl.text = _formatAmountForDisplay(raw);
        }
      });
      rent.currency = currency;
      _rents.add(rent);
    }
  }

  String _normalizeAmountString(String s) {
    if (s.isEmpty) return s;
    // If string uses comma as decimal separator and dots as thousand separators (e.g. '1.234,56'),
    // convert to plain dot decimal ('1234.56'). If only commas present, replace comma with dot.
    final hasComma = s.contains(',');
    final hasDot = s.contains('.');
    String out = s;
    if (hasComma && hasDot) {
      // assume dots are thousand separators
      out = out.replaceAll('.', '').replaceAll(',', '.');
    } else if (hasComma && !hasDot) {
      out = out.replaceAll(',', '.');
    } else {
      // leave as-is (may already be in plain format)
      out = out;
    }
    return out;
  }

  String _formatAmountForDisplay(String raw) {
    if (raw.isEmpty) return raw;
    // ensure raw uses dot as decimal separator
    final norm = raw.replaceAll(',', '.');
    final d = double.tryParse(norm);
    if (d == null) return raw;
    final fixed = d.toStringAsFixed(2); // '1234.00'
    final parts = fixed.split('.');
    var intPart = parts[0];
    final frac = parts.length > 1 ? parts[1] : '00';
    // insert thousand separators (.)
    final sb = StringBuffer();
    int count = 0;
    for (int i = intPart.length - 1; i >= 0; i--) {
      sb.write(intPart[i]);
      count++;
      if (count == 3 && i != 0) {
        sb.write('.');
        count = 0;
      }
    }
    final intWithDots = sb.toString().split('').reversed.join();
    return '$intWithDots,$frac';
  }

  @override
  void dispose() {
    _titleCtrl.dispose();
    _dateCtrl.dispose();
    _amountCtrl.dispose();
    _amountFocus.dispose();
    _supervisorCtrl.dispose();
    _subSupervisorCtrl.dispose();
    for (final p in _participants) {
      p.dispose();
    }
    for (final r in _rents) {
      r.dispose();
    }
    super.dispose();
  }

  void _addParticipant() {
    setState(() => _participants.add(_ParticipantEntry()));
  }

  void _removeParticipant(int index) {
    setState(() {
      final p = _participants[index];
      // if this participant had an existing gid (was previously saved), mark its keys for removal
      if (p.gid != null && p.gid!.isNotEmpty) {
        _removedKeys.add('cont_name**meetcontgroup**${p.gid}');
        _removedKeys.add('cont_phone**meetcontgroup**${p.gid}');
        _removedKeys.add('cont_mail**meetcontgroup**${p.gid}');
      } else {
        // try to discover keys from the original entities by matching values
        final orig = widget.initialFlat?.formEntities;
        if (orig != null) {
          for (final k in orig.keys) {
            if (k.contains('cont_name') && (orig[k]?.toString() ?? '') == p.nameCtrl.text.trim()) {
              _removedKeys.add(k);
              final phoneKeyGuess = k.replaceFirst('cont_name', 'cont_phone');
              if (orig.containsKey(phoneKeyGuess)) _removedKeys.add(phoneKeyGuess);
              final mailKeyGuess = k.replaceFirst('cont_name', 'cont_mail');
              if (orig.containsKey(mailKeyGuess)) _removedKeys.add(mailKeyGuess);
              break;
            }
          }
        }
      }
      p.dispose();
      _participants.removeAt(index);
    });
  }

  void _addRent() {
    final rent = _RentEntry();
    // attach focus listener for new rent amount
    rent.amountFocus.addListener(() {
      if (rent.amountFocus.hasFocus) {
        rent.amountCtrl.text = _normalizeAmountString(rent.amountCtrl.text);
        rent.amountCtrl.selection = TextSelection.fromPosition(TextPosition(offset: rent.amountCtrl.text.length));
      } else {
        final raw = _normalizeAmountString(rent.amountCtrl.text);
        if (raw.isNotEmpty) rent.amountCtrl.text = _formatAmountForDisplay(raw);
      }
    });
    setState(() => _rents.add(rent));
  }

  void _removeRent(int index) {
    setState(() {
      final r = _rents[index];
      if (r.gid != null && r.gid!.isNotEmpty) {
        _removedKeys.add('meet_rent_title**meetrentsgroup**${r.gid}');
        _removedKeys.add('meet_rent**meetrentsgroup**${r.gid}');
        _removedKeys.add('currency**meetrentsgroup**${r.gid}');
      } else {
        final orig = widget.initialFlat?.formEntities;
        if (orig != null) {
          for (final k in orig.keys) {
            if (k.contains('meet_rent_title') && (orig[k]?.toString() ?? '') == r.titleCtrl.text.trim()) {
              _removedKeys.add(k);
              final amountKey = k.replaceFirst('meet_rent_title', 'meet_rent');
              if (orig.containsKey(amountKey)) _removedKeys.add(amountKey);
              final currencyKey = k.replaceFirst('meet_rent_title', 'currency');
              if (orig.containsKey(currencyKey)) _removedKeys.add(currencyKey);
              break;
            }
          }
        }
      }
      r.dispose();
      _rents.removeAt(index);
    });
  }

  Future<void> _selectDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime(2000),
      lastDate: DateTime(2101),
    );
    if (picked != null) {
      setState(() {
        _dateCtrl.text = "${picked.toLocal()}".split(' ')[0];
      });
    }
  }

  Widget _buildParticipantCard(int index) {
    final participant = _participants[index];
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
                Text('İletişim ${index + 1}', style: const TextStyle(fontWeight: FontWeight.w600)),
                IconButton(
                  icon: const Icon(Icons.delete_outline),
                  onPressed: _participants.length > 1 ? () => _removeParticipant(index) : null,
                ),
              ],
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: participant.nameCtrl,
              decoration: const InputDecoration(labelText: 'Name'),
              validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: participant.phoneCtrl,
              decoration: InputDecoration(
                labelText: 'Phone',
                suffixIcon: IconButton(
                  icon: const Icon(Icons.phone),
                  onPressed: () {},
                ),
              ),
              keyboardType: TextInputType.phone,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: participant.emailCtrl,
              decoration: InputDecoration(
                labelText: 'Email',
                suffixIcon: const Icon(Icons.email),
              ),
              keyboardType: TextInputType.emailAddress,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRentCard(int index) {
    final rent = _rents[index];
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
                Text('Rent ${index + 1}', style: const TextStyle(fontWeight: FontWeight.w600)),
                IconButton(
                  icon: const Icon(Icons.delete_outline),
                  onPressed: _rents.length > 1 ? () => _removeRent(index) : null,
                ),
              ],
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: rent.titleCtrl,
              decoration: const InputDecoration(labelText: 'Title'),
              validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: rent.amountCtrl,
              focusNode: rent.amountFocus,
              decoration: const InputDecoration(labelText: 'Amount'),
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9\.,]'))],
            ),
            const SizedBox(height: 8),
            DropdownButtonFormField<String>(
              initialValue: rent.currency,
              decoration: const InputDecoration(labelText: 'Currency'),
              items: ['TRY', 'USD', 'EUR'].map((c) => DropdownMenuItem(value: c, child: Text(c))).toList(),
              onChanged: (v) => setState(() => rent.currency = v!),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;

    final auth = Provider.of<AuthProvider>(context, listen: false);
    final apartmentsProv = Provider.of<ApartmentsProvider>(context, listen: false);
    final flatProv = Provider.of<DocumentProvider>(context, listen: false);

    final selected = apartmentsProv.selectedApartment;
    if (selected == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No apartment selected')),
      );
      return;
    }

    final grpCode = (selected.opKey != null && selected.opKey!.isNotEmpty)
        ? selected.opKey!
        : ((selected.code != null && selected.code!.isNotEmpty) ? selected.code! : 'op-apt-${selected.id}');

    final entities = <String, dynamic>{};

    // Add meeting details
    entities['meet_note'] = _titleCtrl.text.trim();
    entities['meet_date'] = _dateCtrl.text.trim();
    entities['meet_amount'] = _normalizeAmountString(_amountCtrl.text.trim());
    entities['meet_active_supervisor'] = _supervisorCtrl.text.trim();
    entities['meet_active_supervisor_sub'] = _subSupervisorCtrl.text.trim();

    // Add participants (reuse existing gid when editing so entries are updated)
    for (int i = 0; i < _participants.length; i++) {
      final p = _participants[i];
      final gid = p.gid ?? '${DateTime.now().millisecondsSinceEpoch}-$i';
      entities['cont_name**meetcontgroup**$gid'] = p.nameCtrl.text.trim();
      entities['cont_phone**meetcontgroup**$gid'] = p.phoneCtrl.text.trim();
      entities['cont_mail**meetcontgroup**$gid'] = p.emailCtrl.text.trim();
    }

    // Add rents (reuse existing gid when editing so entries are updated)
    for (int i = 0; i < _rents.length; i++) {
      final r = _rents[i];
      final gid = r.gid ?? '${DateTime.now().millisecondsSinceEpoch}-$i';
      entities['meet_rent_title**meetrentsgroup**$gid'] = r.titleCtrl.text.trim();
      entities['meet_rent**meetrentsgroup**$gid'] = _normalizeAmountString(r.amountCtrl.text.trim());
      entities['currency**meetrentsgroup**$gid'] = r.currency;
    }

    // Prepare Flat with formEntities: for updates, merge our newly constructed `entities`
    // into the original `formEntities` so updated values replace originals.
    final mergedEntities = <String, dynamic>{};
    if (widget.initialFlat != null && widget.initialFlat!.formEntities != null) {
      mergedEntities.addAll(widget.initialFlat!.formEntities!);
    }
    // overlay with current values (meeting details, participants, rents)
    mergedEntities.addAll(entities);

    final flat = Flat(
      number: _titleCtrl.text.trim(),
      formEntities: widget.initialFlat != null ? mergedEntities : entities,
    );

    if (widget.initialFlat != null) {
      flat.remoteId = widget.initialFlat!.remoteId;
      flat.formRowId = widget.initialFlat!.formRowId;
      if (_removedKeys.isNotEmpty) {
        final id = widget.initialFlat!.formRowId ?? '';
        flat.removedData = _removedKeys.map((k) => {'id': id, 'type': 'entity', 'key': k}).toList();
      }
    }

    final token = auth.token ?? '';
    if (token.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('No API token available. Please login.')));
      return;
    }

    bool success = false;
    if (widget.initialFlat != null && widget.initialFlat!.formRowId != null && widget.initialFlat!.formRowId!.isNotEmpty) {
      success = await flatProv.updateRemote(flat, grpCode, token, rowKey: widget.initialFlat!.formRowId, formTag: 'op-doc-meeting-form', typeKey: 'op-doc-meeting');
    } else {
      success = await flatProv.createRemote(flat, grpCode, token, formTag: 'op-doc-meeting-form', typeKey: 'op-doc-meeting');
    }

    if (success) {
      if (widget.onSaved != null) {
        widget.onSaved!(flat);
      }
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Meeting saved successfully')));
      if (mounted) {
        Navigator.of(context).pop();
      }
    } else {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to save meeting')));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Create Meeting'),
        actions: [
          IconButton(
            icon: const Icon(Icons.save),
            onPressed: _save,
          ),
        ],
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              controller: _titleCtrl,
              decoration: const InputDecoration(labelText: 'Meeting Notes/Title'),
              validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _dateCtrl,
              decoration: const InputDecoration(labelText: 'Meeting Date'),
              readOnly: true,
              onTap: _selectDate,
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _amountCtrl,
              decoration: const InputDecoration(labelText: 'Total Amount'),
              focusNode: _amountFocus,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9\.,]'))],
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _supervisorCtrl,
              decoration: const InputDecoration(labelText: 'Active Supervisor'),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _subSupervisorCtrl,
              decoration: const InputDecoration(labelText: 'Sub Supervisor'),
            ),
            const SizedBox(height: 16),
            const Text('Participants', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            ..._participants.asMap().entries.map((e) => _buildParticipantCard(e.key)),
            const SizedBox(height: 12),
            ElevatedButton.icon(
              onPressed: _addParticipant,
              icon: const Icon(Icons.add),
              label: const Text('Add Contact'),
            ),
            const SizedBox(height: 16),
            const Text('Rents', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            ..._rents.asMap().entries.map((e) => _buildRentCard(e.key)),
            const SizedBox(height: 12),
            ElevatedButton.icon(
              onPressed: _addRent,
              icon: const Icon(Icons.add),
              label: const Text('Add Rent'),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _save,
                    child: const Text('Save Meeting'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}