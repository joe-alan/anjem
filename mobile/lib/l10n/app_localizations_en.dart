// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get cancel => 'Cancel';

  @override
  String get yes => 'Yes';

  @override
  String get no => 'No';

  @override
  String get ok => 'OK';

  @override
  String get retry => 'Retry';

  @override
  String get close => 'Close';

  @override
  String get back => 'Back';

  @override
  String get continueButton => 'Continue';

  @override
  String get loading => 'Loading...';

  @override
  String get saving => 'Saving...';

  @override
  String get cancelling => 'Cancelling...';

  @override
  String get submit => 'Submit';

  @override
  String get next => 'Next';

  @override
  String get skipForNow => 'Skip for now';

  @override
  String get gotIt => 'Got it!';

  @override
  String get openSettings => 'Open Settings';

  @override
  String get dismiss => 'Dismiss';

  @override
  String get driverFallback => 'Driver';

  @override
  String get yourDriver => 'Your driver';

  @override
  String get riderTagline => 'Your ride, on demand';

  @override
  String get driverTagline => 'Drive and earn on campus';

  @override
  String get signInWithGoogle => 'Sign in with Google';

  @override
  String get signingIn => 'Signing in...';

  @override
  String get termsNoticePrefix => 'By signing in, you agree to our ';

  @override
  String get termsAndConditions => 'Terms & Conditions';

  @override
  String get termsNoticeConnector => ' and ';

  @override
  String get privacyPolicy => 'Privacy Policy';

  @override
  String get termsNoticeSuffix => '';

  @override
  String get continueSessionTitle => 'Continue Previous Session?';

  @override
  String get continueRideMessage =>
      'You have an active ride. Would you like to continue?';

  @override
  String get continuePendingMessage =>
      'You have a pending ride request. Would you like to continue?';

  @override
  String get logoutTitle => 'Logout';

  @override
  String get logoutConfirmMessage => 'Are you sure you want to logout?';

  @override
  String get accountSuspendedBanner =>
      'Your account has been suspended. You cannot request rides.';

  @override
  String get activeRideRequestBanner => 'You have an active ride request';

  @override
  String get viewButton => 'View';

  @override
  String get oneMoreCancellationWarning =>
      'Warning: One more cancellation will suspend your account.';

  @override
  String get repeatedCancellationsWarning =>
      'Warning: Repeated cancellations may result in a temporary ban.';

  @override
  String get accountSuspendedButton => 'Account Suspended';

  @override
  String get requestInProgressButton => 'Request in Progress';

  @override
  String get requestRideButton => 'Request Ride';

  @override
  String get selectLocationsTitle => 'Select Locations';

  @override
  String get searchLocationsHint =>
      'Search locations (gates, canteens, faculties...)';

  @override
  String get pickupLabel => 'Pickup';

  @override
  String get dropOffLabel => 'Drop-off';

  @override
  String get searchingText => 'Searching...';

  @override
  String get startTypingHint => 'Start typing to search for locations';

  @override
  String get noResultsFound => 'No results found';

  @override
  String get locationResolveFailed =>
      'Could not resolve one of the selected locations. Please try searching again.';

  @override
  String estimateFailed(String error) {
    return 'Failed to get estimate: $error';
  }

  @override
  String get beaconLabel => 'Beacon';

  @override
  String get cancelRequestTitle => 'Cancel Request?';

  @override
  String get cancelRequestConfirmMessage =>
      'Are you sure you want to cancel this ride request?';

  @override
  String get yesCancelButton => 'Yes, Cancel';

  @override
  String get findingDriverTitle => 'Finding Driver';

  @override
  String get noDriversAvailable => 'No drivers available right now';

  @override
  String get findingDriverMessage => 'Finding a driver for you...';

  @override
  String retryingInSeconds(int seconds) {
    return 'Retrying in $seconds seconds…';
  }

  @override
  String queuePosition(String position) {
    return 'Queue Position: $position';
  }

  @override
  String get pleaseWaitMessage =>
      'Please wait while we match you with a nearby driver';

  @override
  String get cancelRequestButton => 'Cancel Request';

  @override
  String get driverFoundTitle => 'Driver Found!';

  @override
  String driverOnTheWayMessage(String driverName) {
    return '$driverName is on the way';
  }

  @override
  String get callingDriver => 'Calling driver...';

  @override
  String get callingRider => 'Calling rider...';

  @override
  String get rideCancelledTitle => 'Ride Cancelled';

  @override
  String get driverCancelledMessage => 'Your driver cancelled this ride.';

  @override
  String adminCancelReason(String reason) {
    return 'Admin reason: $reason';
  }

  @override
  String get cancelRideTitle => 'Cancel Ride?';

  @override
  String get cancelRideConfirmMessage =>
      'Are you sure you want to cancel? This may result in a brief cooldown before you can request again.';

  @override
  String cancelFailed(String error) {
    return 'Failed to cancel: $error';
  }

  @override
  String get statusDriverOnTheWay => 'Driver on the way';

  @override
  String get statusDriverArrived => 'Driver has arrived';

  @override
  String get statusRideInProgress => 'Ride in progress';

  @override
  String get statusRideCompleted => 'Ride completed';

  @override
  String get statusRideCancelled => 'Ride cancelled';

  @override
  String etaMinutes(String minutes) {
    return 'ETA: $minutes min';
  }

  @override
  String get rateYourRideTitle => 'Rate Your Ride';

  @override
  String get rideCompletedHeading => 'Ride Completed!';

  @override
  String get fareLabel => 'Fare:';

  @override
  String get fromLabel => 'From:';

  @override
  String get toLabel => 'To:';

  @override
  String get howWasYourRide => 'How was your ride?';

  @override
  String get whatDidYouLike => 'What did you like?';

  @override
  String get additionalFeedbackOptional => 'Additional Feedback (Optional)';

  @override
  String get shareFeedbackHint => 'Share your experience...';

  @override
  String get submitRatingButton => 'Submit Rating';

  @override
  String get tagCleanVehicle => 'Clean Vehicle';

  @override
  String get tagSafeDriving => 'Safe Driving';

  @override
  String get tagFriendlyDriver => 'Friendly Driver';

  @override
  String get tagOnTime => 'On Time';

  @override
  String get tagProfessional => 'Professional';

  @override
  String get tagSmoothRide => 'Smooth Ride';

  @override
  String get tagHelpful => 'Helpful';

  @override
  String get rideHistoryTitle => 'Ride History';

  @override
  String get filterAll => 'All';

  @override
  String get filterCompleted => 'Completed';

  @override
  String get filterCancelled => 'Cancelled';

  @override
  String get noRidesYet => 'No rides yet';

  @override
  String get rideDetailsTitle => 'Ride Details';

  @override
  String get estimatedFareLabel => 'Estimated Fare:';

  @override
  String get passengerCountTitle => 'Passenger Count';

  @override
  String get specialRequestsHint => 'Special Requests (Optional)';

  @override
  String get confirmRequest => 'Confirm Request';

  @override
  String welcomeDriver(String name) {
    return 'Welcome, $name!';
  }

  @override
  String get statusOnlineWithRide => 'You have an active ride';

  @override
  String get statusOnlineIdle => 'Waiting for ride requests...';

  @override
  String get statusOfflineMessage =>
      'Go online to start receiving ride requests';

  @override
  String get joiningQueue => 'Joining queue...';

  @override
  String get youreNextInQueue => 'You’re next in queue!';

  @override
  String queuePositionNumber(int position) {
    return 'Queue Position: #$position';
  }

  @override
  String get nextRideComing => 'The next ride request will come to you';

  @override
  String driversAheadOfYou(int count) {
    return '$count driver(s) ahead of you';
  }

  @override
  String get todaysEarningsTitle => 'Today’s Earnings';

  @override
  String ridesCompletedToday(int count) {
    return '$count rides completed today';
  }

  @override
  String get ratingLabel => 'Rating';

  @override
  String get totalRidesLabel => 'Total Rides';

  @override
  String get statusLabel => 'Status';

  @override
  String get statusSuspended => 'Suspended';

  @override
  String get statusVerified => 'Verified';

  @override
  String get statusUnverified => 'Unverified';

  @override
  String get youHaveActiveRide => 'You have an active ride';

  @override
  String get viewActiveRide => 'View Active Ride';

  @override
  String get accountSuspendedContactAdmin =>
      'Your account has been suspended. Contact admin.';

  @override
  String suspensionReason(String reason) {
    return 'Reason: $reason';
  }

  @override
  String get pendingApprovalBanner =>
      'Your account is pending admin approval. You cannot go online until approved.';

  @override
  String get unableToLoadStats => 'Unable to load statistics';

  @override
  String get noCreditsWarning =>
      'You have no credits. Contact admin to top up before going online.';

  @override
  String lowCreditsWarning(int balance) {
    return 'Low credits: $balance remaining. Contact admin to top up soon.';
  }

  @override
  String get earningsHistoryButton => 'Earnings History';

  @override
  String get settingsButton => 'Settings';

  @override
  String get loadingProfile => 'Loading Profile...';

  @override
  String get goOnlineButton => 'Go Online';

  @override
  String get activeRideButton => 'Active Ride';

  @override
  String get goOfflineButton => 'Go Offline';

  @override
  String get topUpComingSoon => 'Top Up — Coming Soon';

  @override
  String get yourCreditsTitle => 'Your Credits';

  @override
  String get noCreditsBalance => 'No credits';

  @override
  String get lowCreditsBalance => 'Low credits';

  @override
  String get sufficientCreditsBalance => 'Sufficient credits';

  @override
  String get noCreditsMessage =>
      'You cannot go online or accept rides until your balance is topped up. Contact your admin.';

  @override
  String get lowCreditsMessage =>
      'Your balance is running low. You can still accept rides, but consider contacting admin to top up soon.';

  @override
  String get sufficientCreditsMessage =>
      'You have enough credits to go online and accept rides.';

  @override
  String get howCreditsWork => 'How credits work';

  @override
  String get creditInfo1 => '1 credit is deducted each time you accept a ride';

  @override
  String get creditInfo2 => 'You need at least 1 credit to go online';

  @override
  String get creditInfo3 =>
      'Credits are granted by admin — contact them to top up';

  @override
  String creditsChip(int balance) {
    return 'Credits: $balance';
  }

  @override
  String get driverSettingsTitle => 'Driver Settings';

  @override
  String get riderSettingsTitle => 'Settings';

  @override
  String get languageSectionTitle => 'Language';

  @override
  String get languageSectionDesc => 'App display language';

  @override
  String get languageEnglish => 'English';

  @override
  String get languageIndonesian => 'Bahasa Indonesia';

  @override
  String get maxPickupRadiusTitle => 'Max Pickup Radius';

  @override
  String get maxPickupRadiusDesc =>
      'Only receive ride requests where the pickup is within this distance from your current location.';

  @override
  String get failedToLoadSettings => 'Failed to load settings';

  @override
  String get failedToSaveSettings =>
      'Failed to save settings. Please try again.';

  @override
  String get settingsSavedSuccess => 'Settings saved successfully';

  @override
  String get saveSettingsButton => 'Save Settings';

  @override
  String get driverVerificationTitle => 'Driver Verification';

  @override
  String get studentInfoPageTitle => 'Student Information';

  @override
  String get studentInfoPageSubtitle =>
      'Enter your student details for verification';

  @override
  String get vehicleInfoPageTitle => 'Vehicle Information';

  @override
  String get vehicleInfoPageSubtitle => 'Enter your vehicle details';

  @override
  String get ktmPhotoPageTitle => 'Upload KTM Photo';

  @override
  String get ktmPhotoPageSubtitle =>
      'Take a clear photo of your student ID card';

  @override
  String get studentEmailLabel => 'Student Email *';

  @override
  String get studentIdLabel => 'Student ID *';

  @override
  String get fullNameLabel => 'Full Name (as on KTM) *';

  @override
  String get vehicleTypeLabel => 'Vehicle Type *';

  @override
  String get motorcycleOption => 'Motorcycle';

  @override
  String get carOption => 'Car';

  @override
  String get vehicleColorLabel => 'Vehicle Color *';

  @override
  String get vehicleTypeNotSupportedTitle => 'Vehicle Type Not Supported';

  @override
  String get vehicleTypeNotSupportedMessage =>
      'Sorry, we currently only support motorcycles. Car support will be available soon!';

  @override
  String get takePhotoButton => 'Take Photo';

  @override
  String get fromGalleryButton => 'From Gallery';

  @override
  String get pleaseUploadKtm => 'Please upload your KTM photo';

  @override
  String get pleaseSelectColor => 'Please select a vehicle color';

  @override
  String failedToPickImage(String error) {
    return 'Failed to pick image: $error';
  }

  @override
  String get checkingAvailability => 'Checking availability...';

  @override
  String get emailAvailable => '✓ Email is available';

  @override
  String get emailUnavailable => '✗ This email is already registered';

  @override
  String get emailCheckError => 'Could not check availability';

  @override
  String get kycRejectedBannerTitle => 'KYC Rejected';

  @override
  String draftDataLoaded(String timeAgo) {
    return 'Draft data loaded (saved $timeAgo)';
  }

  @override
  String get noPhotoSelected => 'No photo selected';

  @override
  String get ktmPhotoInfo =>
      'Make sure your KTM photo is clear and all details are visible';

  @override
  String get emailVerificationTitle => 'Email Verification';

  @override
  String get verifyYourEmailHeading => 'Verify Your Email';

  @override
  String get verificationCodeSentTo =>
      'We’ve sent a 6-digit verification code to';

  @override
  String get verifyEmailButton => 'Verify Email';

  @override
  String get pleaseEnterCode => 'Please enter the 6-digit code';

  @override
  String get didntReceiveCode => 'Didn’t receive the code? ';

  @override
  String resendCountdown(int seconds) {
    return 'Resend in ${seconds}s';
  }

  @override
  String get resendCode => 'Resend Code';

  @override
  String get verificationCodeExpiry =>
      'The verification code expires in 10 minutes. Please check your spam folder if you don’t see the email.';

  @override
  String get newRideRequestTitle => 'New Ride Request';

  @override
  String get acceptWithinLabel => 'Accept within';

  @override
  String get requestTimedOut => 'Request timed out';

  @override
  String get rideAccepted => 'Ride accepted!';

  @override
  String get rideDeclined => 'Ride declined';

  @override
  String get rideCancelledByRider => 'Ride request was cancelled';

  @override
  String get errorRideCancelledByRider =>
      'This ride request was cancelled by the rider';

  @override
  String get errorRideAlreadyAccepted =>
      'This ride was already accepted by another driver';

  @override
  String get errorInsufficientCredits =>
      'Insufficient credits to accept this ride';

  @override
  String get errorAlreadyActiveRide => 'You already have an active ride';

  @override
  String get errorRideNoLongerAvailable => 'Ride request no longer available';

  @override
  String get errorFailedToAccept => 'Failed to accept ride. Please try again.';

  @override
  String get destinationLabel => 'Destination';

  @override
  String get passengerSingular => 'Passenger';

  @override
  String get passengerPlural => 'Passengers';

  @override
  String get specialRequestsTitle => 'Special Requests';

  @override
  String get acceptRideButton => 'Accept Ride';

  @override
  String get declineButton => 'Decline';

  @override
  String get acceptingRide => 'Accepting ride...';

  @override
  String get locationPermissionTitle => 'Location Permission Required';

  @override
  String get locationPermissionMessage =>
      'This app needs location permission to track your ride and update your position. Please enable location access in Settings.';

  @override
  String get rideCompletedSnackbar => 'Ride completed! 🎉';

  @override
  String get rideCancelledSnackbar => 'Ride cancelled';

  @override
  String get rideCancelledByRiderSnackbar => 'Ride was cancelled by the rider';

  @override
  String get activeRideTitle => 'Active Ride';

  @override
  String get goBackButton => 'Go Back';

  @override
  String get failedToLoadRide => 'Failed to load ride';

  @override
  String statusUpdatedTo(String status) {
    return 'Status updated to $status';
  }

  @override
  String failedToUpdateStatus(String error) {
    return 'Failed to update status: $error';
  }

  @override
  String get markAsArrivedFab => 'Mark as Arrived';

  @override
  String get startRideFab => 'Start Ride';

  @override
  String get completeRideFab => 'Complete Ride';

  @override
  String get cancelRideActiveTitle => 'Cancel Ride';

  @override
  String get cancelRideActiveConfirmMessage =>
      'Are you sure you want to cancel this ride? This may affect your driver rating.';

  @override
  String get noContinueButton => 'No, Continue';

  @override
  String get yesCancelRide => 'Yes, Cancel';

  @override
  String adminOverrideBanner(String reason) {
    return 'Admin Override: $reason';
  }

  @override
  String get driverStatusDrivingToPickup => 'Driving to pickup';

  @override
  String get driverStatusArrivedAtPickup => 'Arrived at pickup';

  @override
  String get driverStatusRideInProgress => 'Ride in progress';

  @override
  String get driverStatusRideCompleted => 'Ride completed';

  @override
  String get driverStatusRideCancelled => 'Ride cancelled';

  @override
  String get logoutTooltip => 'Logout';

  @override
  String get cancelRideTooltip => 'Cancel ride';

  @override
  String get statusOnlineLabel => 'ONLINE';

  @override
  String get statusOfflineLabel => 'OFFLINE';

  @override
  String get riderFallback => 'Rider';

  @override
  String radiusCurrentLabel(String radius) {
    return '$radius km radius';
  }

  @override
  String get radiusMinLabel => '0.5 km';

  @override
  String get radiusMaxLabel => '5 km';

  @override
  String get noMaxRadiusLabel => 'No max pickup radius';

  @override
  String get noMaxRadiusDesc =>
      'Accept all ride requests regardless of distance';

  @override
  String get recenterMap => 'Recenter map';

  @override
  String get dateLabel => 'Date';

  @override
  String get driverLabel => 'Driver';

  @override
  String get maxPassengersHint => 'Max: 4 passengers';

  @override
  String get specialRequestsPlaceholder => 'e.g., \"Please wait at gate 2\"';

  @override
  String failedGeneric(String error) {
    return 'Failed: $error';
  }

  @override
  String get validatorStudentEmailRequired => 'Student email is required';

  @override
  String get validatorInvalidEmailFormat => 'Invalid email format';

  @override
  String get validatorEmailDomainInvalid =>
      'Email must be from students.undip.ac.id domain';

  @override
  String get validatorEmailAlreadyRegistered =>
      'This email is already registered';

  @override
  String get validatorStudentIdRequired => 'Student ID is required';

  @override
  String get validatorFullNameRequired => 'Full name is required';

  @override
  String get validatorRequired => 'Required';

  @override
  String get validatorVehicleColorRequired => 'Vehicle color is required';

  @override
  String get studentEmailHint => 'your.email@students.undip.ac.id';

  @override
  String get studentEmailHelper => 'Must be your university email';

  @override
  String get studentIdHint => 'e.g., 24010123140147';

  @override
  String get fullNameHint => 'Your full name';

  @override
  String get selectColorHint => 'Select vehicle color';

  @override
  String get licensePlateLabel => 'License Plate *';

  @override
  String get licensePlateFormat => 'Format: XX - 1234 - XXX';

  @override
  String get motorcycleOnlyNote => 'Motorcycle rides — maximum 1 passenger';

  @override
  String get profileSectionTitle => 'Profile';

  @override
  String get personalInfoSectionTitle => 'Personal Information';

  @override
  String get nameLabel => 'Name';

  @override
  String get phoneNumberLabel => 'WhatsApp Number';

  @override
  String get phoneNumberHint => 'e.g., 08123456789';

  @override
  String get emailReadOnlyHint => 'From Google account';

  @override
  String get saveProfileButton => 'Save Profile';

  @override
  String get profileSavedSuccess => 'Profile saved successfully';

  @override
  String get failedToSaveProfile => 'Failed to save profile. Please try again.';

  @override
  String get changeProfilePicture => 'Change Profile Picture';

  @override
  String get takePhotoOption => 'Take Photo';

  @override
  String get chooseFromGalleryOption => 'Choose from Gallery';

  @override
  String get uploadingAvatar => 'Uploading photo...';

  @override
  String get avatarUpdatedSuccess => 'Profile picture updated';

  @override
  String get failedToUpdateAvatar => 'Failed to update profile picture';

  @override
  String get vehicleInfoSectionTitle => 'Vehicle Information';

  @override
  String get plateNumberLabel => 'Plate Number';

  @override
  String get colorLabel => 'Color';

  @override
  String get accountSectionTitle => 'Account';

  @override
  String get deleteAccountTitle => 'Delete Account';

  @override
  String get deleteAccountConfirmTitle => 'Delete Your Account?';

  @override
  String get deleteAccountConfirmMessage =>
      'This action is permanent. All your data will be deleted. Type DELETE to confirm.';

  @override
  String get deleteAccountButton => 'Delete Account';

  @override
  String get deleteAccountSuccess => 'Account deleted successfully';

  @override
  String get failedToDeleteAccount =>
      'Failed to delete account. Please try again.';

  @override
  String get typeDeleteToConfirm => 'Type DELETE to confirm';

  @override
  String get aboutSectionTitle => 'About';

  @override
  String get appVersionLabel => 'App Version';

  @override
  String get rideSettingsSectionTitle => 'Ride Settings';

  @override
  String get phoneRequiredTitle => 'WhatsApp number required';

  @override
  String get phoneRequiredMessage =>
      'Please add your WhatsApp number in Settings before requesting a ride. Drivers will contact you via WhatsApp.';

  @override
  String get goToSettings => 'Go to Settings';

  @override
  String get openWhatsAppTitle => 'Open WhatsApp';

  @override
  String openWhatsAppMessage(String name) {
    return 'Chat with $name on WhatsApp?';
  }

  @override
  String get whatsAppUnavailable =>
      'WhatsApp number not available for this user';

  @override
  String get noActiveDriversMessage => 'No active drivers available right now.';

  @override
  String get waitOrCancelMessage =>
      'You can wait or cancel — we won’t hold you.';

  @override
  String get noDriversAcceptedRound => 'No nearby drivers accepted yet.';

  @override
  String retryingCountdown(int seconds) {
    return 'Still searching… new drivers may join. ${seconds}s remaining.';
  }

  @override
  String get driverUnavailableTryingNext =>
      'That driver’s unavailable — trying the next one';

  @override
  String notifyingDriverMinutesAway(int minutes) {
    return 'Notifying a driver $minutes minutes away…';
  }

  @override
  String get legendBeingNotified => 'Being notified';

  @override
  String get legendActiveDriver => 'Active driver';

  @override
  String get legendUnavailable => 'Unavailable';
}
