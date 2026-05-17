import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../api/api_service.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Map<String, dynamic>? _stats;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchStats();
  }

  void _fetchStats() async {
    final stats = await context.read<ApiService>().getDashboardStats();
    if (mounted) {
      setState(() {
        _stats = stats != null ? {'stats': stats} : null;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<ApiService>().user;
    final statsList = (_stats?['stats'] as List?) ?? [];

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard', style: TextStyle(fontWeight: FontWeight.bold)),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () {
              context.read<ApiService>().logout();
              Navigator.pushReplacementNamed(context, '/login');
            },
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => _fetchStats(),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Welcome Card
              Container(
                padding: const EdgeInsets.all(24),
                width: double.infinity,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF13198F), Color(0xFF3F51B5)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF13198F).withOpacity(0.3),
                      blurRadius: 10,
                      offset: const Offset(0, 5),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Welcome back,',
                      style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 16),
                    ),
                    Text(
                      user?['name'] ?? 'User',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.white24,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        user?['school_name'] ?? 'Institute',
                        style: const TextStyle(color: Colors.white, fontSize: 12),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 32),

              // Stats Grid
              const Text(
                'Quick Stats',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 16),
              if (_isLoading)
                const Center(child: CircularProgressIndicator())
              else if (statsList.isEmpty)
                const Text('No stats available')
              else
                GridView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    crossAxisSpacing: 16,
                    mainAxisSpacing: 16,
                    childAspectRatio: 1.5,
                  ),
                  itemCount: statsList.length,
                  itemBuilder: (context, index) {
                    final item = statsList[index];
                    return _buildStatCard(item);
                  },
                ),

              const SizedBox(height: 32),

              // Quick Actions
              const Text(
                'Features',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 16),
              _buildFeatureList(user?['role']),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStatCard(Map<String, dynamic> item) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.black12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Row(
            children: [
              Icon(_getIconData(item['icon']), size: 16, color: Colors.pinkAccent),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  item['label'],
                  style: const TextStyle(fontSize: 12, color: Colors.grey),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            item['value'].toString(),
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
          ),
        ],
      ),
    );
  }

  Widget _buildFeatureList(String? role) {
    List<Map<String, dynamic>> features = [];
    
    if (role == 'student') {
      features = [
        {'title': 'My Results', 'icon': Icons.assignment, 'color': Colors.blue},
        {'title': 'Attendance', 'icon': Icons.how_to_reg, 'color': Colors.green},
        {'title': 'Timetable', 'icon': Icons.calendar_month, 'color': Colors.orange},
        {'title': 'Take CBT', 'icon': Icons.computer, 'color': Colors.purple},
      ];
    } else if (role == 'teacher') {
      features = [
        {'title': 'Mark Attendance', 'icon': Icons.qr_code_scanner, 'color': Colors.green},
        {'title': 'Enter Marks', 'icon': Icons.edit_note, 'color': Colors.blue},
        {'title': 'My Classes', 'icon': Icons.groups, 'color': Colors.orange},
        {'title': 'Reports', 'icon': Icons.bar_chart, 'color': Colors.red},
      ];
    } else {
      features = [
        {'title': 'Fee Records', 'icon': Icons.payments, 'color': Colors.green},
        {'title': 'Attendance', 'icon': Icons.history, 'color': Colors.blue},
        {'title': 'Result Checker', 'icon': Icons.fact_check, 'color': Colors.orange},
      ];
    }

    return ListView.separated(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: features.length,
      separatorBuilder: (context, index) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        final f = features[index];
        return ListTile(
          onTap: () {},
          leading: Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: (f['color'] as Color).withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(f['icon'], color: f['color']),
          ),
          title: Text(f['title'], style: const TextStyle(fontWeight: FontWeight.w600)),
          trailing: const Icon(Icons.chevron_right, size: 20),
          tileColor: Theme.of(context).cardColor,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        );
      },
    );
  }

  IconData _getIconData(String? name) {
    switch (name) {
      case 'calendar_today': return Icons.calendar_today;
      case 'book': return Icons.book;
      case 'account_balance_wallet': return Icons.account_balance_wallet;
      case 'class': return Icons.class_;
      case 'people': return Icons.people;
      case 'check_circle': return Icons.check_circle;
      case 'school': return Icons.school;
      case 'payments': return Icons.payments;
      default: return Icons.info_outline;
    }
  }
}
