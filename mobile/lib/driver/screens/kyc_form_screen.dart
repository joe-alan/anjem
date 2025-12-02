import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../../core/providers/kyc_provider.dart';
import '../../core/config/app_config.dart';
import 'email_verification_screen.dart';

// License plate formatter for Indonesian format: X 1111 XXX
class LicensePlateFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final text = newValue.text.toUpperCase().replaceAll(' ', '');

    if (text.isEmpty) {
      return newValue.copyWith(text: '');
    }

    String formatted = '';
    int cursorPosition = newValue.selection.end;

    // Format: X 1111 XXX (1-2 letters, space, 1-4 digits, space, 1-3 letters)
    for (int i = 0; i < text.length && i < 9; i++) {
      if (i == 2 || i == 6) {
        formatted += ' ';
        if (cursorPosition > i) cursorPosition++;
      }
      formatted += text[i];
    }

    return TextEditingValue(
      text: formatted,
      selection: TextSelection.collapsed(
        offset: cursorPosition.clamp(0, formatted.length),
      ),
    );
  }
}

class KycFormScreen extends ConsumerStatefulWidget {
  const KycFormScreen({super.key});

  @override
  ConsumerState<KycFormScreen> createState() => _KycFormScreenState();
}

class _KycFormScreenState extends ConsumerState<KycFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _pageController = PageController();
  int _currentPage = 0;

  // Form fields
  final _studentEmailController = TextEditingController();
  final _studentIdController = TextEditingController();
  final _studentNameController = TextEditingController();

  // License plate split into 3 parts
  final _plateArea1Controller = TextEditingController(); // e.g., "B" or "AB"
  final _plateNumberController = TextEditingController(); // e.g., "1234"
  final _plateArea2Controller = TextEditingController(); // e.g., "XYZ"

  final _vehicleColorController = TextEditingController();

  String _vehicleType = 'motorcycle';
  String _vehicleColor = '';
  File? _ktmPhoto;

  final _imagePicker = ImagePicker();

  // Predefined vehicle colors
  final List<String> _vehicleColors = [
    'Black',
    'White',
    'Silver',
    'Gray',
    'Red',
    'Blue',
    'Green',
    'Yellow',
    'Brown',
    'Orange',
    'Purple',
    'Gold',
    'Other',
  ];

  // Email availability check state
  bool _checkingEmail = false;
  String? _emailAvailabilityMessage;
  bool? _emailAvailable;

  @override
  void initState() {
    super.initState();
    _loadDraftData();
  }

  Future<void> _loadDraftData() async {
    try {
      final kycProvider = ref.read(kycStateProvider.notifier);
      final draft = await kycProvider.loadDraft();

      if (draft != null && mounted) {
        // Auto-fill text controllers
        _studentEmailController.text = draft['student_email'] ?? '';
        _studentIdController.text = draft['student_id'] ?? '';
        _studentNameController.text = draft['student_name'] ?? '';

        // Parse license plate if exists
        final plate = draft['vehicle_plate'] as String?;
        if (plate != null && plate.isNotEmpty) {
          final parts = plate.split(' ');
          if (parts.length == 3) {
            _plateArea1Controller.text = parts[0];
            _plateNumberController.text = parts[1];
            _plateArea2Controller.text = parts[2];
          }
        }

        // Set dropdowns
        final vehicleType = draft['vehicle_type'] as String?;
        if (vehicleType != null) {
          _vehicleType = vehicleType;
        }

        final vehicleColor = draft['vehicle_color'] as String?;
        if (vehicleColor != null) {
          _vehicleColor = vehicleColor;
        }

        // Jump to last page
        final lastPage = draft['current_page'] ?? 0;
        if (lastPage > 0 && lastPage <= 2) {
          _pageController.jumpToPage(lastPage);
          _currentPage = lastPage;
        }

        setState(() {});

        // Show snackbar
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Draft data loaded (saved ${_formatDate(draft['saved_at'])})'),
              backgroundColor: Colors.blue,
              duration: const Duration(seconds: 3),
            ),
          );
        }
      }
    } catch (e) {
      print('Error loading draft: $e');
    }
  }

  String _formatDate(String? isoString) {
    if (isoString == null) return 'recently';
    try {
      final date = DateTime.parse(isoString);
      final now = DateTime.now();
      final diff = now.difference(date);

      if (diff.inMinutes < 1) return 'just now';
      if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
      if (diff.inHours < 24) return '${diff.inHours}h ago';
      return '${diff.inDays}d ago';
    } catch (e) {
      return 'recently';
    }
  }

  @override
  void dispose() {
    _studentEmailController.dispose();
    _studentIdController.dispose();
    _studentNameController.dispose();
    _plateArea1Controller.dispose();
    _plateNumberController.dispose();
    _plateArea2Controller.dispose();
    _vehicleColorController.dispose();
    _pageController.dispose();
    super.dispose();
  }

  Future<void> _pickKtmPhoto(ImageSource source) async {
    try {
      final pickedFile = await _imagePicker.pickImage(
        source: source,
        maxWidth: 1920,
        maxHeight: 1080,
        imageQuality: 85,
      );

      if (pickedFile != null) {
        setState(() {
          _ktmPhoto = File(pickedFile.path);
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to pick image: ${e.toString()}'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _nextPage() async {
    if (_currentPage < 2) {
      // Save draft before moving to next page
      await _saveDraft();

      _pageController.nextPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
    }
  }

  Future<void> _saveDraft() async {
    try {
      final kycProvider = ref.read(kycStateProvider.notifier);

      // Combine license plate parts
      String? vehiclePlate;
      if (_plateArea1Controller.text.isNotEmpty ||
          _plateNumberController.text.isNotEmpty ||
          _plateArea2Controller.text.isNotEmpty) {
        vehiclePlate = '${_plateArea1Controller.text.trim()} '
            '${_plateNumberController.text.trim()} '
            '${_plateArea2Controller.text.trim()}';
      }

      await kycProvider.saveDraft(
        studentEmail: _studentEmailController.text.trim().isNotEmpty
            ? _studentEmailController.text.trim()
            : null,
        studentId: _studentIdController.text.trim().isNotEmpty
            ? _studentIdController.text.trim()
            : null,
        studentName: _studentNameController.text.trim().isNotEmpty
            ? _studentNameController.text.trim()
            : null,
        vehicleType: _vehicleType.isNotEmpty ? _vehicleType : null,
        vehiclePlate: vehiclePlate,
        vehicleColor: _vehicleColor.isNotEmpty ? _vehicleColor : null,
        currentPage: _currentPage,
      );
    } catch (e) {
      print('Error saving draft: $e');
    }
  }

  Future<void> _checkEmailAvailability(String email) async {
    // Basic validation first
    if (email.isEmpty || !email.contains('@')) {
      setState(() {
        _emailAvailabilityMessage = null;
        _emailAvailable = null;
        _checkingEmail = false;
      });
      return;
    }

    // Check domain
    if (!email.toLowerCase().endsWith('@students.undip.ac.id')) {
      setState(() {
        _emailAvailabilityMessage = null;
        _emailAvailable = null;
        _checkingEmail = false;
      });
      return;
    }

    // Check availability
    setState(() {
      _checkingEmail = true;
      _emailAvailabilityMessage = 'Checking availability...';
      _emailAvailable = null;
    });

    try {
      final kycService = ref.read(kycServiceProvider);
      final isAvailable = await kycService.checkEmailAvailability(email);

      if (mounted) {
        setState(() {
          _checkingEmail = false;
          _emailAvailable = isAvailable;
          _emailAvailabilityMessage = isAvailable
              ? '✓ Email is available'
              : '✗ This email is already registered';
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _checkingEmail = false;
          _emailAvailabilityMessage = 'Could not check availability';
          _emailAvailable = null;
        });
      }
    }
  }

  void _previousPage() {
    if (_currentPage > 0) {
      _pageController.previousPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
    }
  }

  Future<void> _submitKyc() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (_ktmPhoto == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please upload your KTM photo'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    if (_vehicleColor.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please select a vehicle color'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    // Combine license plate parts: "B 1234 XYZ"
    final licensePlate = '${_plateArea1Controller.text.trim().toUpperCase()} '
        '${_plateNumberController.text.trim()} '
        '${_plateArea2Controller.text.trim().toUpperCase()}';

    final success = await ref.read(kycStateProvider.notifier).submitKyc(
      studentEmail: _studentEmailController.text.trim(),
      studentId: _studentIdController.text.trim(),
      studentName: _studentNameController.text.trim(),
      vehicleType: _vehicleType,
      vehiclePlate: licensePlate,
      vehicleColor: _vehicleColor,
      ktmPhoto: _ktmPhoto!,
    );

    if (success && mounted) {
      // Navigate to email verification screen
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (context) => EmailVerificationScreen(
            studentEmail: _studentEmailController.text.trim(),
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final kycState = ref.watch(kycStateProvider);

    // Show error messages
    ref.listen<KycState>(kycStateProvider, (previous, next) {
      if (next.error != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.error!),
            backgroundColor: Colors.red,
            action: SnackBarAction(
              label: 'Dismiss',
              textColor: Colors.white,
              onPressed: () {
                ref.read(kycStateProvider.notifier).clearError();
              },
            ),
          ),
        );
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Driver Verification'),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
      ),
      body: Form(
        key: _formKey,
        child: Column(
          children: [
            // Progress indicator
            LinearProgressIndicator(
              value: (_currentPage + 1) / 3,
              backgroundColor: Colors.grey[200],
              valueColor: AlwaysStoppedAnimation<Color>(config.primaryColor),
            ),

            // Page content
            Expanded(
              child: PageView(
                controller: _pageController,
                physics: const NeverScrollableScrollPhysics(),
                onPageChanged: (page) {
                  setState(() {
                    _currentPage = page;
                  });
                },
                children: [
                  _buildStudentInfoPage(),
                  _buildVehicleInfoPage(),
                  _buildKtmPhotoPage(),
                ],
              ),
            ),

            // Navigation buttons
            Padding(
              padding: const EdgeInsets.all(16.0),
              child: Row(
                children: [
                  if (_currentPage > 0)
                    Expanded(
                      child: OutlinedButton(
                        onPressed: kycState.isLoading ? null : _previousPage,
                        child: const Text('Back'),
                      ),
                    ),
                  if (_currentPage > 0) const SizedBox(width: 16),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: kycState.isLoading
                          ? null
                          : () {
                              if (_currentPage < 2) {
                                if (_validateCurrentPage()) {
                                  _nextPage();
                                }
                              } else {
                                _submitKyc();
                              }
                            },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: config.primaryColor,
                        foregroundColor: Colors.white,
                      ),
                      child: kycState.isLoading
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor: AlwaysStoppedAnimation<Color>(
                                  Colors.white,
                                ),
                              ),
                            )
                          : Text(_currentPage < 2 ? 'Next' : 'Submit'),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  bool _validateCurrentPage() {
    switch (_currentPage) {
      case 0:
        // Validate with form key for page 0
        return _formKey.currentState?.validate() ?? false;
      case 1:
        // Validate all 3 license plate parts and vehicle color
        return _plateArea1Controller.text.trim().isNotEmpty &&
            _plateNumberController.text.trim().isNotEmpty &&
            _plateArea2Controller.text.trim().isNotEmpty &&
            _vehicleColor.isNotEmpty;
      case 2:
        return _ktmPhoto != null;
      default:
        return false;
    }
  }

  void _showCarNotSupportedDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Vehicle Type Not Supported'),
        content: const Text(
          'Sorry, we currently only support motorcycles. Car support will be available soon!',
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.of(context).pop();
              setState(() {
                _vehicleType = 'motorcycle';
              });
            },
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  Widget _buildStudentInfoPage() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Student Information',
            style: TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Enter your student details for verification',
            style: TextStyle(
              fontSize: 14,
              color: Colors.grey,
            ),
          ),
          const SizedBox(height: 32),

          TextFormField(
            controller: _studentEmailController,
            keyboardType: TextInputType.emailAddress,
            decoration: InputDecoration(
              labelText: 'Student Email *',
              hintText: 'your.email@students.undip.ac.id',
              prefixIcon: const Icon(Icons.email),
              border: const OutlineInputBorder(),
              helperText: _emailAvailabilityMessage ?? 'Must be your university email',
              helperStyle: TextStyle(
                color: _emailAvailable == true
                    ? Colors.green
                    : _emailAvailable == false
                        ? Colors.red
                        : null,
              ),
              suffixIcon: _checkingEmail
                  ? const Padding(
                      padding: EdgeInsets.all(12.0),
                      child: SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                    )
                  : _emailAvailable != null
                      ? Icon(
                          _emailAvailable! ? Icons.check_circle : Icons.error,
                          color: _emailAvailable! ? Colors.green : Colors.red,
                        )
                      : null,
            ),
            onChanged: (value) {
              // Debounce the check - wait 500ms after user stops typing
              Future.delayed(const Duration(milliseconds: 500), () {
                if (_studentEmailController.text == value) {
                  _checkEmailAvailability(value);
                }
              });
            },
            validator: (value) {
              if (value == null || value.trim().isEmpty) {
                return 'Student email is required';
              }
              if (!value.contains('@')) {
                return 'Invalid email format';
              }
              // Check domain
              if (!value.toLowerCase().endsWith('@students.undip.ac.id')) {
                return 'Email must be from students.undip.ac.id domain';
              }
              // Check if email is available (this is shown via helperText, but also block submission)
              if (_emailAvailable == false) {
                return 'This email is already registered';
              }
              return null;
            },
          ),
          const SizedBox(height: 16),

          TextFormField(
            controller: _studentIdController,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
              labelText: 'Student ID *',
              hintText: 'e.g., 24010123140147',
              prefixIcon: Icon(Icons.badge),
              border: OutlineInputBorder(),
            ),
            validator: (value) {
              if (value == null || value.trim().isEmpty) {
                return 'Student ID is required';
              }
              return null;
            },
          ),
          const SizedBox(height: 16),

          TextFormField(
            controller: _studentNameController,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(
              labelText: 'Full Name (as on KTM) *',
              hintText: 'Your full name',
              prefixIcon: Icon(Icons.person),
              border: OutlineInputBorder(),
            ),
            validator: (value) {
              if (value == null || value.trim().isEmpty) {
                return 'Full name is required';
              }
              return null;
            },
          ),
        ],
      ),
    );
  }

  Widget _buildVehicleInfoPage() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Vehicle Information',
            style: TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Enter your vehicle details',
            style: TextStyle(
              fontSize: 14,
              color: Colors.grey,
            ),
          ),
          const SizedBox(height: 32),

          const Text(
            'Vehicle Type *',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: RadioListTile<String>(
                  title: const Text('Motorcycle'),
                  value: 'motorcycle',
                  groupValue: _vehicleType,
                  onChanged: (value) {
                    setState(() {
                      _vehicleType = value!;
                    });
                  },
                ),
              ),
              Expanded(
                child: RadioListTile<String>(
                  title: const Text('Car'),
                  value: 'car',
                  groupValue: _vehicleType,
                  onChanged: (value) {
                    if (value == 'car') {
                      _showCarNotSupportedDialog();
                    } else {
                      setState(() {
                        _vehicleType = value!;
                      });
                    }
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),

          const Text(
            'License Plate *',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              // First box: 1-2 letters (e.g., "B" or "AB")
              Expanded(
                flex: 2,
                child: TextFormField(
                  controller: _plateArea1Controller,
                  textCapitalization: TextCapitalization.characters,
                  textAlign: TextAlign.center,
                  maxLength: 2,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                  decoration: const InputDecoration(
                    counterText: '',
                    hintText: 'B',
                    border: OutlineInputBorder(),
                    contentPadding: EdgeInsets.symmetric(vertical: 16),
                  ),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z]')),
                  ],
                  validator: (value) {
                    if (value == null || value.trim().isEmpty) {
                      return 'Required';
                    }
                    return null;
                  },
                ),
              ),
              const SizedBox(width: 8),

              // Second box: 1-4 digits (e.g., "1234")
              Expanded(
                flex: 3,
                child: TextFormField(
                  controller: _plateNumberController,
                  textAlign: TextAlign.center,
                  keyboardType: TextInputType.number,
                  maxLength: 4,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                  decoration: const InputDecoration(
                    counterText: '',
                    hintText: '1234',
                    border: OutlineInputBorder(),
                    contentPadding: EdgeInsets.symmetric(vertical: 16),
                  ),
                  inputFormatters: [
                    FilteringTextInputFormatter.digitsOnly,
                  ],
                  validator: (value) {
                    if (value == null || value.trim().isEmpty) {
                      return 'Required';
                    }
                    return null;
                  },
                ),
              ),
              const SizedBox(width: 8),

              // Third box: 1-3 letters (e.g., "XYZ")
              Expanded(
                flex: 2,
                child: TextFormField(
                  controller: _plateArea2Controller,
                  textCapitalization: TextCapitalization.characters,
                  textAlign: TextAlign.center,
                  maxLength: 3,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                  decoration: const InputDecoration(
                    counterText: '',
                    hintText: 'XYZ',
                    border: OutlineInputBorder(),
                    contentPadding: EdgeInsets.symmetric(vertical: 16),
                  ),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z]')),
                  ],
                  validator: (value) {
                    if (value == null || value.trim().isEmpty) {
                      return 'Required';
                    }
                    return null;
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            'Format: XX - 1234 - XXX',
            style: TextStyle(
              fontSize: 12,
              color: Colors.grey[600],
            ),
          ),
          const SizedBox(height: 16),

          DropdownButtonFormField<String>(
            decoration: const InputDecoration(
              labelText: 'Vehicle Color *',
              prefixIcon: Icon(Icons.palette),
              border: OutlineInputBorder(),
            ),
            hint: const Text('Select vehicle color'),
            items: _vehicleColors.map((color) {
              return DropdownMenuItem(
                value: color,
                child: Text(color),
              );
            }).toList(),
            value: _vehicleColor.isEmpty ? null : _vehicleColor,
            onChanged: (value) {
              setState(() {
                _vehicleColor = value ?? '';
              });
            },
            validator: (value) {
              if (value == null || value.isEmpty) {
                return 'Vehicle color is required';
              }
              return null;
            },
          ),
        ],
      ),
    );
  }

  Widget _buildKtmPhotoPage() {
    final config = AppConfig.instance;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(24.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Upload KTM Photo',
            style: TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Take a clear photo of your student ID card',
            style: TextStyle(
              fontSize: 14,
              color: Colors.grey,
            ),
          ),
          const SizedBox(height: 32),

          if (_ktmPhoto != null)
            Container(
              width: double.infinity,
              height: 300,
              decoration: BoxDecoration(
                border: Border.all(color: config.primaryColor, width: 2),
                borderRadius: BorderRadius.circular(12),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Image.file(
                  _ktmPhoto!,
                  fit: BoxFit.cover,
                ),
              ),
            )
          else
            Container(
              width: double.infinity,
              height: 300,
              decoration: BoxDecoration(
                border: Border.all(color: Colors.grey[300]!, width: 2),
                borderRadius: BorderRadius.circular(12),
                color: Colors.grey[50],
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.credit_card,
                    size: 80,
                    color: Colors.grey[400],
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'No photo selected',
                    style: TextStyle(
                      fontSize: 16,
                      color: Colors.grey[600],
                    ),
                  ),
                ],
              ),
            ),

          const SizedBox(height: 24),

          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _pickKtmPhoto(ImageSource.camera),
                  icon: const Icon(Icons.camera_alt),
                  label: const Text('Take Photo'),
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _pickKtmPhoto(ImageSource.gallery),
                  icon: const Icon(Icons.photo_library),
                  label: const Text('From Gallery'),
                ),
              ),
            ],
          ),

          const SizedBox(height: 24),

          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.blue[50],
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: Colors.blue[200]!),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.info_outline, color: Colors.blue[700]),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Make sure your KTM photo is clear and all details are visible',
                    style: TextStyle(
                      fontSize: 14,
                      color: Colors.blue[700],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
