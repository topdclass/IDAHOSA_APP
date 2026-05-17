import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../api/api_service.dart';

class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key});

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> {
  bool _isLoading = true;
  List<dynamic> _attendanceLogs = [];

  @override
  void initState() {
    super.initState();
    _fetchAttendance();
  }

  void _fetchAttendance() async {
    // This method would call a new getAttendance() in ApiService
    // For now, let's mock it or assume it's implemented
    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Attendance History'),
        elevation: 0,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _attendanceLogs.isEmpty
              ? _buildEmptyState()
              : _buildLogsList(),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () {
          // Navigate to QR Scanner
        },
        label: const Text('Scan QR'),
        icon: const Icon(Icons.qr_code_scanner),
        backgroundColor: const Color(0xFF13198F),
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.calendar_today_outlined, size: 80, color: Colors.grey[300]),
          const SizedBox(height: 16),
          const Text('No attendance records found', style: TextStyle(color: Colors.grey)),
        ],
      ),
    );
  }

  Widget _buildLogsList() {
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _attendanceLogs.length,
      itemBuilder: (context, index) {
        final log = _attendanceLogs[index];
        return Card(
          margin: const EdgeInsets.only(bottom: 12),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          child: ListTile(
            leading: const CircleAvatar(
              backgroundColor: Colors.green,
              child: Icon(Icons.check, color: Colors.white),
            ),
            title: Text(log['attendance_date']),
            subtitle: Text('Time In: ${log['clock_in']}'),
            trailing: const Text('Present', style: TextStyle(color: Colors.green, fontWeight: FontWeight.bold)),
          ),
        );
      },
    );
  }
}
