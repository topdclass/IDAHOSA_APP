class UserModel {
  final int id;
  final String name;
  final String username;
  final String role;
  final int? schoolId;
  final String schoolName;

  UserModel({
    required this.id,
    required this.name,
    required this.username,
    required this.role,
    this.schoolId,
    required this.schoolName,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'],
      name: json['name'],
      username: json['username'] ?? '',
      role: json['role'],
      schoolId: json['school_id'],
      schoolName: json['school_name'] ?? 'RosmonSMS',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'username': username,
      'role': role,
      'school_id': schoolId,
      'school_name': schoolName,
    };
  }
}
