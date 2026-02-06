import 'dart:convert';

import 'package:flutter/material.dart';
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

  void dispose() {
    titleCtrl.dispose();
    amountCtrl.dispose();
  }
}

class _MeetingCreatePageState extends State<MeetingCreatePage> {
  final _formKey = GlobalKey<FormState>();
  final _titleCtrl = TextEditingController();
  final _dateCtrl = TextEditingController();
  final _amountCtrl = TextEditingController();
  final _supervisorCtrl = TextEditingController();
  final _subSupervisorCtrl = TextEditingController();
  final List<_ParticipantEntry> _participants = [];
  final List<_RentEntry> _rents = [];

  @override
  void initState() {
    super.initState();
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
    if (attrs.containsKey('meet_amount')) _amountCtrl.text = attrs['meet_amount']!;
    if (attrs.containsKey('meet_active_supervisor')) _supervisorCtrl.text = attrs['meet_active_supervisor']!;
    if (attrs.containsKey('meet_active_supervisor_sub')) _subSupervisorCtrl.text = attrs['meet_active_supervisor_sub']!;

    // Load participants
    final participantKeys = attrs.keys.where((k) => k.contains('cont_name**meetcontgroup')).toList();
    for (final key in participantKeys) {
      final name = attrs[key] ?? '';
      final phoneKey = key.replaceFirst('cont_name', 'cont_phone');
      final emailKey = key.replaceFirst('cont_name', 'cont_mail');
      final phone = attrs[phoneKey] ?? '';
      final email = attrs[emailKey] ?? '';
      
      final participant = _ParticipantEntry();
      participant.nameCtrl.text = name;
      participant.phoneCtrl.text = phone;
      participant.emailCtrl.text = email;
      _participants.add(participant);
    }

    // Load rents
    final rentKeys = attrs.keys.where((k) => k.contains('meet_rent_title**meetrentsgroup')).toList();
    for (final key in rentKeys) {
      final title = attrs[key] ?? '';
      final amountKey = key.replaceFirst('meet_rent_title', 'meet_rent');
      final currencyKey = key.replaceFirst('meet_rent_title', 'currency');
      final amount = attrs[amountKey] ?? '';
      final currency = attrs[currencyKey] ?? 'TRY';
      
      final rent = _RentEntry();
      rent.titleCtrl.text = title;
      rent.amountCtrl.text = amount;
      rent.currency = currency;
      _rents.add(rent);
    }
  }

  @override
  void dispose() {
    _titleCtrl.dispose();
    _dateCtrl.dispose();
    _amountCtrl.dispose();
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
      _participants[index].dispose();
      _participants.removeAt(index);
    });
  }

  void _addRent() {
    setState(() => _rents.add(_RentEntry()));
  }

  void _removeRent(int index) {
    setState(() {
      _rents[index].dispose();
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
                Text('Participant ${index + 1}', style: const TextStyle(fontWeight: FontWeight.w600)),
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
              decoration: const InputDecoration(labelText: 'Amount'),
              keyboardType: TextInputType.number,
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
    entities['meet_amount'] = _amountCtrl.text.trim();
    entities['meet_active_supervisor'] = _supervisorCtrl.text.trim();
    entities['meet_active_supervisor_sub'] = _subSupervisorCtrl.text.trim();

    // Add participants
    for (int i = 0; i < _participants.length; i++) {
      final p = _participants[i];
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      final gid = '$timestamp-$i';
      entities['cont_name**meetcontgroup**$gid'] = p.nameCtrl.text.trim();
      entities['cont_phone**meetcontgroup**$gid'] = p.phoneCtrl.text.trim();
      entities['cont_mail**meetcontgroup**$gid'] = p.emailCtrl.text.trim();
    }

    // Add rents
    for (int i = 0; i < _rents.length; i++) {
      final r = _rents[i];
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      final gid = '$timestamp-$i';
      entities['meet_rent_title**meetrentsgroup**$gid'] = r.titleCtrl.text.trim();
      entities['meet_rent**meetrentsgroup**$gid'] = r.amountCtrl.text.trim();
      entities['currency**meetrentsgroup**$gid'] = r.currency;
    }

    final flat = Flat(
      number: _titleCtrl.text.trim(),
      formEntities: entities,
    );

    if (widget.initialFlat != null) {
      flat.remoteId = widget.initialFlat!.remoteId;
      flat.formRowId = widget.initialFlat!.formRowId;
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
              keyboardType: TextInputType.number,
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
              label: const Text('Add Participant'),
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