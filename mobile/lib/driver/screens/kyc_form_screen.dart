import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:mobile/l10n/app_localizations.dart';
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
  final _phoneController = TextEditingController();

  // License plate split into 3 parts
  final _plateArea1Controller = TextEditingController(); // e.g., "B" or "AB"
  final _plateNumberController = TextEditingController(); // e.g., "1234"
  final _plateArea2Controller = TextEditingController(); // e.g., "XYZ"

  final _vehicleColorController = TextEditingController();

  String _vehicleType = 'motorcycle';
  String _vehicleColor = '';
  File? _ktmPhoto;
  File? _profilePhoto;

  final _imagePicker = ImagePicker();

  bool _isSubmitting = false;

  // Vehicle color keys (English, stored in backend)
  static const List<String> _vehicleColorKeys = [
    'Black', 'White', 'Silver', 'Gray', 'Red', 'Blue', 'Green',
    'Yellow', 'Brown', 'Orange', 'Purple', 'Gold', 'Other',
  ];

  /// Map color key to localized display name
  String _colorDisplayName(String key, AppLocalizations l10n) {
    switch (key) {
      case 'Black': return l10n.colorBlack;
      case 'White': return l10n.colorWhite;
      case 'Silver': return l10n.colorSilver;
      case 'Gray': return l10n.colorGray;
      case 'Red': return l10n.colorRed;
      case 'Blue': return l10n.colorBlue;
      case 'Green': return l10n.colorGreen;
      case 'Yellow': return l10n.colorYellow;
      case 'Brown': return l10n.colorBrown;
      case 'Orange': return l10n.colorOrange;
      case 'Purple': return l10n.colorPurple;
      case 'Gold': return l10n.colorGold;
      case 'Other': return l10n.colorOther;
      default: return key;
    }
  }

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
        _phoneController.text = draft['phone_number'] ?? '';

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
          final l10n = AppLocalizations.of(context);
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(l10n.draftDataLoaded(_formatDate(draft['saved_at'] as String?))),
              backgroundColor: Colors.blue,
              duration: const Duration(seconds: 3),
            ),
          );
        }
      }
    } catch (e) {
      if (kDebugMode) print('Error loading draft: $e');
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
    _phoneController.dispose();
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
        final l10n = AppLocalizations.of(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(l10n.failedToPickImage(e.toString())),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _pickProfilePhoto(ImageSource source) async {
    try {
      final pickedFile = await _imagePicker.pickImage(
        source: source,
        maxWidth: 512,
        maxHeight: 512,
        imageQuality: 85,
      );

      if (pickedFile != null) {
        setState(() {
          _profilePhoto = File(pickedFile.path);
        });
      }
    } catch (e) {
      if (mounted) {
        final l10n = AppLocalizations.of(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(l10n.failedToPickImage(e.toString())),
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
        phoneNumber: _phoneController.text.trim().isNotEmpty
            ? _phoneController.text.trim()
            : null,
        vehicleType: _vehicleType.isNotEmpty ? _vehicleType : null,
        vehiclePlate: vehiclePlate,
        vehicleColor: _vehicleColor.isNotEmpty ? _vehicleColor : null,
        currentPage: _currentPage,
      );
    } catch (e) {
      if (kDebugMode) print('Error saving draft: $e');
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

    // Read l10n before async gap
    final l10n = AppLocalizations.of(context);

    // Check availability
    setState(() {
      _checkingEmail = true;
      _emailAvailabilityMessage = l10n.checkingAvailability;
      _emailAvailable = null;
    });

    try {
      final kycService = ref.read(kycServiceProvider);
      final result = await kycService.checkEmailAvailability(email);

      if (mounted) {
        final l10nPost = AppLocalizations.of(context);
        String message;
        if (result.available) {
          message = l10nPost.emailAvailable;
        } else if (result.reason == 'invalid_domain') {
          message = l10nPost.emailInvalidDomain;
        } else {
          message = l10nPost.emailUnavailable;
        }
        setState(() {
          _checkingEmail = false;
          _emailAvailable = result.available;
          _emailAvailabilityMessage = message;
        });
      }
    } catch (e) {
      if (mounted) {
        final l10nPost = AppLocalizations.of(context);
        setState(() {
          _checkingEmail = false;
          _emailAvailabilityMessage = l10nPost.emailCheckError;
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
    if (_formKey.currentState?.validate() == false) {
      return;
    }

    final l10n = AppLocalizations.of(context);

    if (_ktmPhoto == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(l10n.pleaseUploadKtm),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    if (_profilePhoto == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(l10n.profilePhotoRequired),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    if (_vehicleColor.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(l10n.pleaseSelectColor),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      // Combine license plate parts: "B 1234 XYZ"
      final licensePlate =
          '${_plateArea1Controller.text.trim().toUpperCase()} '
          '${_plateNumberController.text.trim()} '
          '${_plateArea2Controller.text.trim().toUpperCase()}';

      final success = await ref.read(kycStateProvider.notifier).submitKyc(
            studentEmail: _studentEmailController.text.trim(),
            studentId: _studentIdController.text.trim(),
            studentName: _studentNameController.text.trim(),
            phoneNumber: _phoneController.text.trim(),
            vehicleType: _vehicleType,
            vehiclePlate: licensePlate,
            vehicleColor: _vehicleColor,
            ktmPhoto: _ktmPhoto!,
            profilePhoto: _profilePhoto!,
          );

      if (success && mounted) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (context) => EmailVerificationScreen(
              studentEmail: _studentEmailController.text.trim(),
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);

    // Show error messages
    ref.listen<KycState>(kycStateProvider, (previous, next) {
      if (next.error != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.error!),
            backgroundColor: Colors.red,
            action: SnackBarAction(
              label: l10n.dismiss,
              textColor: Colors.white,
              onPressed: () {
                if (mounted) {
                  ref.read(kycStateProvider.notifier).clearError();
                }
              },
            ),
          ),
        );
      }
    });

    final rejectionReason =
        ref.watch(kycStatusProvider)?.rejectionReason;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.driverVerificationTitle),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
      ),
      body: Column(
          children: [
            // Rejection reason banner
            if (rejectionReason != null)
              Container(
                width: double.infinity,
                color: Colors.red[50],
                padding: const EdgeInsets.symmetric(
                    horizontal: 16, vertical: 12),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(Icons.cancel_outlined,
                        color: Colors.red, size: 20),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            l10n.kycRejectedBannerTitle,
                            style: const TextStyle(
                              color: Colors.red,
                              fontWeight: FontWeight.bold,
                              fontSize: 13,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            rejectionReason,
                            style: TextStyle(
                                color: Colors.red[700], fontSize: 13),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

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
                  _buildStudentInfoPage(l10n),
                  _buildVehicleInfoPage(l10n),
                  _buildKtmPhotoPage(l10n),
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
                        onPressed: _isSubmitting ? null : _previousPage,
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size.fromHeight(50),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        child: Text(l10n.back),
                      ),
                    ),
                  if (_currentPage > 0) const SizedBox(width: 16),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: _isSubmitting
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
                      child: _isSubmitting
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
                          : Text(_currentPage < 2 ? l10n.next : l10n.submit),
                    ),
                  ),
                ],
              ),
            ),
          ],
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
        return _ktmPhoto != null && _profilePhoto != null;
      default:
        return false;
    }
  }

  void _showCarNotSupportedDialog() {
    showDialog(
      context: context,
      builder: (context) {
        final l10n = AppLocalizations.of(context);
        return AlertDialog(
          title: Text(l10n.vehicleTypeNotSupportedTitle),
          content: Text(l10n.vehicleTypeNotSupportedMessage),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.of(context).pop();
                setState(() {
                  _vehicleType = 'motorcycle';
                });
              },
              child: Text(l10n.ok),
            ),
          ],
        );
      },
    );
  }

  Widget _buildStudentInfoPage(AppLocalizations l10n) {
    return Form(
      key: _formKey,
      child: SingleChildScrollView(
      padding: const EdgeInsets.all(24.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l10n.studentInfoPageTitle,
            style: const TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            l10n.studentInfoPageSubtitle,
            style: const TextStyle(
              fontSize: 14,
              color: Colors.grey,
            ),
          ),
          const SizedBox(height: 32),
          TextFormField(
            controller: _studentEmailController,
            keyboardType: TextInputType.emailAddress,
            decoration: InputDecoration(
              labelText: l10n.studentEmailLabel,
              hintText: l10n.studentEmailHint,
              prefixIcon: const Icon(Icons.email),
              border: const OutlineInputBorder(),
              helperText:
                  _emailAvailabilityMessage ?? l10n.studentEmailHelper,
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
                return l10n.validatorStudentEmailRequired;
              }
              if (!value.contains('@')) {
                return l10n.validatorInvalidEmailFormat;
              }
              if (!RegExp(r'^.+@.+\.ac\.id$', caseSensitive: false).hasMatch(value)) {
                return l10n.validatorEmailDomainInvalid;
              }
              // Check if email is available (this is shown via helperText, but also block submission)
              if (_emailAvailable == false) {
                return l10n.validatorEmailAlreadyRegistered;
              }
              return null;
            },
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _studentIdController,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(
              labelText: l10n.studentIdLabel,
              hintText: l10n.studentIdHint,
              prefixIcon: const Icon(Icons.badge),
              border: const OutlineInputBorder(),
            ),
            validator: (value) {
              if (value == null || value.trim().isEmpty) {
                return l10n.validatorStudentIdRequired;
              }
              return null;
            },
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _studentNameController,
            textCapitalization: TextCapitalization.words,
            decoration: InputDecoration(
              labelText: l10n.fullNameLabel,
              hintText: l10n.fullNameHint,
              prefixIcon: const Icon(Icons.person),
              border: const OutlineInputBorder(),
            ),
            validator: (value) {
              if (value == null || value.trim().isEmpty) {
                return l10n.validatorFullNameRequired;
              }
              return null;
            },
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _phoneController,
            keyboardType: TextInputType.phone,
            decoration: InputDecoration(
              labelText: l10n.whatsappNumberLabel,
              hintText: l10n.whatsappNumberHint,
              prefixIcon: const Icon(Icons.phone),
              border: const OutlineInputBorder(),
              helperText: l10n.whatsappNumberHelper,
            ),
            inputFormatters: [
              FilteringTextInputFormatter.allow(RegExp(r'[0-9+]')),
            ],
            validator: (value) {
              if (value == null || value.trim().isEmpty) {
                return l10n.validatorWhatsappRequired;
              }
              final cleaned = value.trim().replaceAll(RegExp(r'[\s\-]'), '');
              if (cleaned.length < 10 || cleaned.length > 15) {
                return l10n.validatorWhatsappInvalid;
              }
              return null;
            },
          ),
        ],
      ),
    ));
  }

  Widget _buildVehicleInfoPage(AppLocalizations l10n) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l10n.vehicleInfoPageTitle,
            style: const TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            l10n.vehicleInfoPageSubtitle,
            style: const TextStyle(
              fontSize: 14,
              color: Colors.grey,
            ),
          ),
          const SizedBox(height: 32),
          Text(
            l10n.vehicleTypeLabel,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: RadioListTile<String>(
                  title: Text(l10n.motorcycleOption),
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
                  title: Text(l10n.carOption),
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
          Text(
            l10n.licensePlateLabel,
            style: const TextStyle(
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
                      return l10n.validatorRequired;
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
                      return l10n.validatorRequired;
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
                      return l10n.validatorRequired;
                    }
                    return null;
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            l10n.licensePlateFormat,
            style: TextStyle(
              fontSize: 12,
              color: Colors.grey[600],
            ),
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            decoration: InputDecoration(
              labelText: l10n.vehicleColorLabel,
              prefixIcon: const Icon(Icons.palette),
              border: const OutlineInputBorder(),
            ),
            hint: Text(l10n.selectColorHint),
            items: _vehicleColorKeys.map((color) {
              return DropdownMenuItem(
                value: color,
                child: Text(_colorDisplayName(color, l10n)),
              );
            }).toList(),
            initialValue: _vehicleColor.isEmpty ? null : _vehicleColor,
            onChanged: (value) {
              setState(() {
                _vehicleColor = value ?? '';
              });
            },
            validator: (value) {
              if (value == null || value.isEmpty) {
                return l10n.validatorVehicleColorRequired;
              }
              return null;
            },
          ),
        ],
      ),
    );
  }

  Widget _buildKtmPhotoPage(AppLocalizations l10n) {
    final config = AppConfig.instance;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(24.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l10n.ktmPhotoPageTitle,
            style: const TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            l10n.ktmPhotoPageSubtitle,
            style: const TextStyle(
              fontSize: 14,
              color: Colors.grey,
            ),
          ),
          const SizedBox(height: 24),

          // Profile photo picker
          Text(
            l10n.profilePhotoLabel,
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 4),
          Text(
            l10n.profilePhotoHelper,
            style: TextStyle(fontSize: 13, color: Colors.grey[600]),
          ),
          const SizedBox(height: 12),
          Center(
            child: GestureDetector(
              onTap: () {
                showModalBottomSheet(
                  context: context,
                  builder: (ctx) => SafeArea(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        ListTile(
                          leading: const Icon(Icons.camera_alt),
                          title: Text(l10n.takePhotoButton),
                          onTap: () {
                            Navigator.pop(ctx);
                            _pickProfilePhoto(ImageSource.camera);
                          },
                        ),
                        ListTile(
                          leading: const Icon(Icons.photo_library),
                          title: Text(l10n.fromGalleryButton),
                          onTap: () {
                            Navigator.pop(ctx);
                            _pickProfilePhoto(ImageSource.gallery);
                          },
                        ),
                      ],
                    ),
                  ),
                );
              },
              child: Stack(
                children: [
                  CircleAvatar(
                    radius: 50,
                    backgroundColor: Colors.grey[200],
                    backgroundImage:
                        _profilePhoto != null ? FileImage(_profilePhoto!) : null,
                    child: _profilePhoto == null
                        ? Icon(Icons.person, size: 50, color: Colors.grey[400])
                        : null,
                  ),
                  Positioned(
                    bottom: 0,
                    right: 0,
                    child: Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: config.primaryColor,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.camera_alt, size: 18, color: Colors.white),
                    ),
                  ),
                ],
              ),
            ),
          ),

          const SizedBox(height: 28),

          // KTM photo section header
          Text(
            l10n.ktmPhotoLabel,
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 12),
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
                    l10n.noPhotoSelected,
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
                  label: Text(l10n.takePhotoButton),
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _pickKtmPhoto(ImageSource.gallery),
                  icon: const Icon(Icons.photo_library),
                  label: Text(l10n.fromGalleryButton),
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
                    l10n.ktmPhotoInfo,
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
