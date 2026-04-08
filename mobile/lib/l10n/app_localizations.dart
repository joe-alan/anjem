import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_en.dart';
import 'app_localizations_id.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
      : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
    delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
  ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('en'),
    Locale('id')
  ];

  /// No description provided for @cancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get cancel;

  /// No description provided for @yes.
  ///
  /// In en, this message translates to:
  /// **'Yes'**
  String get yes;

  /// No description provided for @no.
  ///
  /// In en, this message translates to:
  /// **'No'**
  String get no;

  /// No description provided for @ok.
  ///
  /// In en, this message translates to:
  /// **'OK'**
  String get ok;

  /// No description provided for @retry.
  ///
  /// In en, this message translates to:
  /// **'Retry'**
  String get retry;

  /// No description provided for @close.
  ///
  /// In en, this message translates to:
  /// **'Close'**
  String get close;

  /// No description provided for @back.
  ///
  /// In en, this message translates to:
  /// **'Back'**
  String get back;

  /// No description provided for @continueButton.
  ///
  /// In en, this message translates to:
  /// **'Continue'**
  String get continueButton;

  /// No description provided for @loading.
  ///
  /// In en, this message translates to:
  /// **'Loading...'**
  String get loading;

  /// No description provided for @saving.
  ///
  /// In en, this message translates to:
  /// **'Saving...'**
  String get saving;

  /// No description provided for @cancelling.
  ///
  /// In en, this message translates to:
  /// **'Cancelling...'**
  String get cancelling;

  /// No description provided for @submit.
  ///
  /// In en, this message translates to:
  /// **'Submit'**
  String get submit;

  /// No description provided for @next.
  ///
  /// In en, this message translates to:
  /// **'Next'**
  String get next;

  /// No description provided for @skipForNow.
  ///
  /// In en, this message translates to:
  /// **'Skip for now'**
  String get skipForNow;

  /// No description provided for @gotIt.
  ///
  /// In en, this message translates to:
  /// **'Got it!'**
  String get gotIt;

  /// No description provided for @openSettings.
  ///
  /// In en, this message translates to:
  /// **'Open Settings'**
  String get openSettings;

  /// No description provided for @dismiss.
  ///
  /// In en, this message translates to:
  /// **'Dismiss'**
  String get dismiss;

  /// No description provided for @driverFallback.
  ///
  /// In en, this message translates to:
  /// **'Driver'**
  String get driverFallback;

  /// No description provided for @yourDriver.
  ///
  /// In en, this message translates to:
  /// **'Your driver'**
  String get yourDriver;

  /// No description provided for @riderTagline.
  ///
  /// In en, this message translates to:
  /// **'Your ride, on demand'**
  String get riderTagline;

  /// No description provided for @driverTagline.
  ///
  /// In en, this message translates to:
  /// **'Drive and earn on campus'**
  String get driverTagline;

  /// No description provided for @signInWithGoogle.
  ///
  /// In en, this message translates to:
  /// **'Sign in with Google'**
  String get signInWithGoogle;

  /// No description provided for @signingIn.
  ///
  /// In en, this message translates to:
  /// **'Signing in...'**
  String get signingIn;

  /// No description provided for @termsNoticePrefix.
  ///
  /// In en, this message translates to:
  /// **'By signing in, you agree to our '**
  String get termsNoticePrefix;

  /// No description provided for @termsAndConditions.
  ///
  /// In en, this message translates to:
  /// **'Terms & Conditions'**
  String get termsAndConditions;

  /// No description provided for @termsNoticeConnector.
  ///
  /// In en, this message translates to:
  /// **' and '**
  String get termsNoticeConnector;

  /// No description provided for @privacyPolicy.
  ///
  /// In en, this message translates to:
  /// **'Privacy Policy'**
  String get privacyPolicy;

  /// No description provided for @termsNoticeSuffix.
  ///
  /// In en, this message translates to:
  /// **''**
  String get termsNoticeSuffix;

  /// No description provided for @continueSessionTitle.
  ///
  /// In en, this message translates to:
  /// **'Continue Previous Session?'**
  String get continueSessionTitle;

  /// No description provided for @continueRideMessage.
  ///
  /// In en, this message translates to:
  /// **'You have an active ride. Would you like to continue?'**
  String get continueRideMessage;

  /// No description provided for @continuePendingMessage.
  ///
  /// In en, this message translates to:
  /// **'You have a pending ride request. Would you like to continue?'**
  String get continuePendingMessage;

  /// No description provided for @logoutTitle.
  ///
  /// In en, this message translates to:
  /// **'Logout'**
  String get logoutTitle;

  /// No description provided for @logoutConfirmMessage.
  ///
  /// In en, this message translates to:
  /// **'Are you sure you want to logout?'**
  String get logoutConfirmMessage;

  /// No description provided for @accountSuspendedBanner.
  ///
  /// In en, this message translates to:
  /// **'Your account has been suspended. You cannot request rides.'**
  String get accountSuspendedBanner;

  /// No description provided for @activeRideRequestBanner.
  ///
  /// In en, this message translates to:
  /// **'You have an active ride request'**
  String get activeRideRequestBanner;

  /// No description provided for @viewButton.
  ///
  /// In en, this message translates to:
  /// **'View'**
  String get viewButton;

  /// No description provided for @oneMoreCancellationWarning.
  ///
  /// In en, this message translates to:
  /// **'Warning: One more cancellation will suspend your account.'**
  String get oneMoreCancellationWarning;

  /// No description provided for @repeatedCancellationsWarning.
  ///
  /// In en, this message translates to:
  /// **'Warning: Repeated cancellations may result in a temporary ban.'**
  String get repeatedCancellationsWarning;

  /// No description provided for @accountSuspendedButton.
  ///
  /// In en, this message translates to:
  /// **'Account Suspended'**
  String get accountSuspendedButton;

  /// No description provided for @requestInProgressButton.
  ///
  /// In en, this message translates to:
  /// **'Request in Progress'**
  String get requestInProgressButton;

  /// No description provided for @requestRideButton.
  ///
  /// In en, this message translates to:
  /// **'Request Ride'**
  String get requestRideButton;

  /// No description provided for @selectLocationsTitle.
  ///
  /// In en, this message translates to:
  /// **'Select Locations'**
  String get selectLocationsTitle;

  /// No description provided for @searchLocationsHint.
  ///
  /// In en, this message translates to:
  /// **'Search locations (gates, canteens, faculties...)'**
  String get searchLocationsHint;

  /// No description provided for @pickupLabel.
  ///
  /// In en, this message translates to:
  /// **'Pickup'**
  String get pickupLabel;

  /// No description provided for @dropOffLabel.
  ///
  /// In en, this message translates to:
  /// **'Drop-off'**
  String get dropOffLabel;

  /// No description provided for @searchingText.
  ///
  /// In en, this message translates to:
  /// **'Searching...'**
  String get searchingText;

  /// No description provided for @startTypingHint.
  ///
  /// In en, this message translates to:
  /// **'Start typing to search for locations'**
  String get startTypingHint;

  /// No description provided for @noResultsFound.
  ///
  /// In en, this message translates to:
  /// **'No results found'**
  String get noResultsFound;

  /// No description provided for @locationResolveFailed.
  ///
  /// In en, this message translates to:
  /// **'Could not resolve one of the selected locations. Please try searching again.'**
  String get locationResolveFailed;

  /// No description provided for @estimateFailed.
  ///
  /// In en, this message translates to:
  /// **'Failed to get estimate: {error}'**
  String estimateFailed(String error);

  /// No description provided for @beaconLabel.
  ///
  /// In en, this message translates to:
  /// **'Beacon'**
  String get beaconLabel;

  /// No description provided for @cancelRequestTitle.
  ///
  /// In en, this message translates to:
  /// **'Cancel Request?'**
  String get cancelRequestTitle;

  /// No description provided for @cancelRequestConfirmMessage.
  ///
  /// In en, this message translates to:
  /// **'Are you sure you want to cancel this ride request?'**
  String get cancelRequestConfirmMessage;

  /// No description provided for @yesCancelButton.
  ///
  /// In en, this message translates to:
  /// **'Yes, Cancel'**
  String get yesCancelButton;

  /// No description provided for @findingDriverTitle.
  ///
  /// In en, this message translates to:
  /// **'Finding Driver'**
  String get findingDriverTitle;

  /// No description provided for @noDriversAvailable.
  ///
  /// In en, this message translates to:
  /// **'No drivers available right now'**
  String get noDriversAvailable;

  /// No description provided for @findingDriverMessage.
  ///
  /// In en, this message translates to:
  /// **'Finding a driver for you...'**
  String get findingDriverMessage;

  /// No description provided for @retryingInSeconds.
  ///
  /// In en, this message translates to:
  /// **'Retrying in {seconds} seconds…'**
  String retryingInSeconds(int seconds);

  /// No description provided for @queuePosition.
  ///
  /// In en, this message translates to:
  /// **'Queue Position: {position}'**
  String queuePosition(String position);

  /// No description provided for @pleaseWaitMessage.
  ///
  /// In en, this message translates to:
  /// **'Please wait while we match you with a nearby driver'**
  String get pleaseWaitMessage;

  /// No description provided for @cancelRequestButton.
  ///
  /// In en, this message translates to:
  /// **'Cancel Request'**
  String get cancelRequestButton;

  /// No description provided for @driverFoundTitle.
  ///
  /// In en, this message translates to:
  /// **'Driver Found!'**
  String get driverFoundTitle;

  /// No description provided for @driverOnTheWayMessage.
  ///
  /// In en, this message translates to:
  /// **'{driverName} is on the way'**
  String driverOnTheWayMessage(String driverName);

  /// No description provided for @callingDriver.
  ///
  /// In en, this message translates to:
  /// **'Calling driver...'**
  String get callingDriver;

  /// No description provided for @callingRider.
  ///
  /// In en, this message translates to:
  /// **'Calling rider...'**
  String get callingRider;

  /// No description provided for @rideCancelledTitle.
  ///
  /// In en, this message translates to:
  /// **'Ride Cancelled'**
  String get rideCancelledTitle;

  /// No description provided for @driverCancelledMessage.
  ///
  /// In en, this message translates to:
  /// **'Your driver cancelled this ride.'**
  String get driverCancelledMessage;

  /// No description provided for @adminCancelReason.
  ///
  /// In en, this message translates to:
  /// **'Admin reason: {reason}'**
  String adminCancelReason(String reason);

  /// No description provided for @cancelRideTitle.
  ///
  /// In en, this message translates to:
  /// **'Cancel Ride?'**
  String get cancelRideTitle;

  /// No description provided for @cancelRideConfirmMessage.
  ///
  /// In en, this message translates to:
  /// **'Are you sure you want to cancel? This may result in a brief cooldown before you can request again.'**
  String get cancelRideConfirmMessage;

  /// No description provided for @cancelFailed.
  ///
  /// In en, this message translates to:
  /// **'Failed to cancel: {error}'**
  String cancelFailed(String error);

  /// No description provided for @statusDriverOnTheWay.
  ///
  /// In en, this message translates to:
  /// **'Driver on the way'**
  String get statusDriverOnTheWay;

  /// No description provided for @statusDriverArrived.
  ///
  /// In en, this message translates to:
  /// **'Driver has arrived'**
  String get statusDriverArrived;

  /// No description provided for @statusRideInProgress.
  ///
  /// In en, this message translates to:
  /// **'Ride in progress'**
  String get statusRideInProgress;

  /// No description provided for @statusRideCompleted.
  ///
  /// In en, this message translates to:
  /// **'Ride completed'**
  String get statusRideCompleted;

  /// No description provided for @statusRideCancelled.
  ///
  /// In en, this message translates to:
  /// **'Ride cancelled'**
  String get statusRideCancelled;

  /// No description provided for @etaMinutes.
  ///
  /// In en, this message translates to:
  /// **'ETA: {minutes} min'**
  String etaMinutes(String minutes);

  /// No description provided for @rateYourRideTitle.
  ///
  /// In en, this message translates to:
  /// **'Rate Your Ride'**
  String get rateYourRideTitle;

  /// No description provided for @rideCompletedHeading.
  ///
  /// In en, this message translates to:
  /// **'Ride Completed!'**
  String get rideCompletedHeading;

  /// No description provided for @fareLabel.
  ///
  /// In en, this message translates to:
  /// **'Fare:'**
  String get fareLabel;

  /// No description provided for @fromLabel.
  ///
  /// In en, this message translates to:
  /// **'From:'**
  String get fromLabel;

  /// No description provided for @toLabel.
  ///
  /// In en, this message translates to:
  /// **'To:'**
  String get toLabel;

  /// No description provided for @howWasYourRide.
  ///
  /// In en, this message translates to:
  /// **'How was your ride?'**
  String get howWasYourRide;

  /// No description provided for @whatDidYouLike.
  ///
  /// In en, this message translates to:
  /// **'What did you like?'**
  String get whatDidYouLike;

  /// No description provided for @additionalFeedbackOptional.
  ///
  /// In en, this message translates to:
  /// **'Additional Feedback (Optional)'**
  String get additionalFeedbackOptional;

  /// No description provided for @shareFeedbackHint.
  ///
  /// In en, this message translates to:
  /// **'Share your experience...'**
  String get shareFeedbackHint;

  /// No description provided for @submitRatingButton.
  ///
  /// In en, this message translates to:
  /// **'Submit Rating'**
  String get submitRatingButton;

  /// No description provided for @tagCleanVehicle.
  ///
  /// In en, this message translates to:
  /// **'Clean Vehicle'**
  String get tagCleanVehicle;

  /// No description provided for @tagSafeDriving.
  ///
  /// In en, this message translates to:
  /// **'Safe Driving'**
  String get tagSafeDriving;

  /// No description provided for @tagFriendlyDriver.
  ///
  /// In en, this message translates to:
  /// **'Friendly Driver'**
  String get tagFriendlyDriver;

  /// No description provided for @tagOnTime.
  ///
  /// In en, this message translates to:
  /// **'On Time'**
  String get tagOnTime;

  /// No description provided for @tagProfessional.
  ///
  /// In en, this message translates to:
  /// **'Professional'**
  String get tagProfessional;

  /// No description provided for @tagSmoothRide.
  ///
  /// In en, this message translates to:
  /// **'Smooth Ride'**
  String get tagSmoothRide;

  /// No description provided for @tagHelpful.
  ///
  /// In en, this message translates to:
  /// **'Helpful'**
  String get tagHelpful;

  /// No description provided for @rideHistoryTitle.
  ///
  /// In en, this message translates to:
  /// **'Ride History'**
  String get rideHistoryTitle;

  /// No description provided for @filterAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get filterAll;

  /// No description provided for @filterCompleted.
  ///
  /// In en, this message translates to:
  /// **'Completed'**
  String get filterCompleted;

  /// No description provided for @filterCancelled.
  ///
  /// In en, this message translates to:
  /// **'Cancelled'**
  String get filterCancelled;

  /// No description provided for @noRidesYet.
  ///
  /// In en, this message translates to:
  /// **'No rides yet'**
  String get noRidesYet;

  /// No description provided for @rideDetailsTitle.
  ///
  /// In en, this message translates to:
  /// **'Ride Details'**
  String get rideDetailsTitle;

  /// No description provided for @estimatedFareLabel.
  ///
  /// In en, this message translates to:
  /// **'Estimated Fare:'**
  String get estimatedFareLabel;

  /// No description provided for @passengerCountTitle.
  ///
  /// In en, this message translates to:
  /// **'Passenger Count'**
  String get passengerCountTitle;

  /// No description provided for @specialRequestsHint.
  ///
  /// In en, this message translates to:
  /// **'Special Requests (Optional)'**
  String get specialRequestsHint;

  /// No description provided for @confirmRequest.
  ///
  /// In en, this message translates to:
  /// **'Confirm Request'**
  String get confirmRequest;

  /// No description provided for @welcomeDriver.
  ///
  /// In en, this message translates to:
  /// **'Welcome, {name}!'**
  String welcomeDriver(String name);

  /// No description provided for @statusOnlineWithRide.
  ///
  /// In en, this message translates to:
  /// **'You have an active ride'**
  String get statusOnlineWithRide;

  /// No description provided for @statusOnlineIdle.
  ///
  /// In en, this message translates to:
  /// **'Waiting for ride requests...'**
  String get statusOnlineIdle;

  /// No description provided for @statusOfflineMessage.
  ///
  /// In en, this message translates to:
  /// **'Go online to start receiving ride requests'**
  String get statusOfflineMessage;

  /// No description provided for @joiningQueue.
  ///
  /// In en, this message translates to:
  /// **'Joining queue...'**
  String get joiningQueue;

  /// No description provided for @youreNextInQueue.
  ///
  /// In en, this message translates to:
  /// **'You’re next in queue!'**
  String get youreNextInQueue;

  /// No description provided for @queuePositionNumber.
  ///
  /// In en, this message translates to:
  /// **'Queue Position: #{position}'**
  String queuePositionNumber(int position);

  /// No description provided for @nextRideComing.
  ///
  /// In en, this message translates to:
  /// **'The next ride request will come to you'**
  String get nextRideComing;

  /// No description provided for @driversAheadOfYou.
  ///
  /// In en, this message translates to:
  /// **'{count} driver(s) ahead of you'**
  String driversAheadOfYou(int count);

  /// No description provided for @todaysEarningsTitle.
  ///
  /// In en, this message translates to:
  /// **'Today’s Earnings'**
  String get todaysEarningsTitle;

  /// No description provided for @ridesCompletedToday.
  ///
  /// In en, this message translates to:
  /// **'{count} rides completed today'**
  String ridesCompletedToday(int count);

  /// No description provided for @ratingLabel.
  ///
  /// In en, this message translates to:
  /// **'Rating'**
  String get ratingLabel;

  /// No description provided for @totalRidesLabel.
  ///
  /// In en, this message translates to:
  /// **'Total Rides'**
  String get totalRidesLabel;

  /// No description provided for @statusLabel.
  ///
  /// In en, this message translates to:
  /// **'Status'**
  String get statusLabel;

  /// No description provided for @statusSuspended.
  ///
  /// In en, this message translates to:
  /// **'Suspended'**
  String get statusSuspended;

  /// No description provided for @statusVerified.
  ///
  /// In en, this message translates to:
  /// **'Verified'**
  String get statusVerified;

  /// No description provided for @statusUnverified.
  ///
  /// In en, this message translates to:
  /// **'Unverified'**
  String get statusUnverified;

  /// No description provided for @youHaveActiveRide.
  ///
  /// In en, this message translates to:
  /// **'You have an active ride'**
  String get youHaveActiveRide;

  /// No description provided for @viewActiveRide.
  ///
  /// In en, this message translates to:
  /// **'View Active Ride'**
  String get viewActiveRide;

  /// No description provided for @accountSuspendedContactAdmin.
  ///
  /// In en, this message translates to:
  /// **'Your account has been suspended. Contact admin.'**
  String get accountSuspendedContactAdmin;

  /// No description provided for @suspensionReason.
  ///
  /// In en, this message translates to:
  /// **'Reason: {reason}'**
  String suspensionReason(String reason);

  /// No description provided for @pendingApprovalBanner.
  ///
  /// In en, this message translates to:
  /// **'Your account is pending admin approval. You cannot go online until approved.'**
  String get pendingApprovalBanner;

  /// No description provided for @unableToLoadStats.
  ///
  /// In en, this message translates to:
  /// **'Unable to load statistics'**
  String get unableToLoadStats;

  /// No description provided for @noCreditsWarning.
  ///
  /// In en, this message translates to:
  /// **'You have no credits. Contact admin to top up before going online.'**
  String get noCreditsWarning;

  /// No description provided for @lowCreditsWarning.
  ///
  /// In en, this message translates to:
  /// **'Low credits: {balance} remaining. Contact admin to top up soon.'**
  String lowCreditsWarning(int balance);

  /// No description provided for @earningsHistoryButton.
  ///
  /// In en, this message translates to:
  /// **'Earnings History'**
  String get earningsHistoryButton;

  /// No description provided for @settingsButton.
  ///
  /// In en, this message translates to:
  /// **'Settings'**
  String get settingsButton;

  /// No description provided for @loadingProfile.
  ///
  /// In en, this message translates to:
  /// **'Loading Profile...'**
  String get loadingProfile;

  /// No description provided for @goOnlineButton.
  ///
  /// In en, this message translates to:
  /// **'Go Online'**
  String get goOnlineButton;

  /// No description provided for @activeRideButton.
  ///
  /// In en, this message translates to:
  /// **'Active Ride'**
  String get activeRideButton;

  /// No description provided for @goOfflineButton.
  ///
  /// In en, this message translates to:
  /// **'Go Offline'**
  String get goOfflineButton;

  /// No description provided for @topUpComingSoon.
  ///
  /// In en, this message translates to:
  /// **'Top Up — Coming Soon'**
  String get topUpComingSoon;

  /// No description provided for @yourCreditsTitle.
  ///
  /// In en, this message translates to:
  /// **'Your Credits'**
  String get yourCreditsTitle;

  /// No description provided for @noCreditsBalance.
  ///
  /// In en, this message translates to:
  /// **'No credits'**
  String get noCreditsBalance;

  /// No description provided for @lowCreditsBalance.
  ///
  /// In en, this message translates to:
  /// **'Low credits'**
  String get lowCreditsBalance;

  /// No description provided for @sufficientCreditsBalance.
  ///
  /// In en, this message translates to:
  /// **'Sufficient credits'**
  String get sufficientCreditsBalance;

  /// No description provided for @noCreditsMessage.
  ///
  /// In en, this message translates to:
  /// **'You cannot go online or accept rides until your balance is topped up. Contact your admin.'**
  String get noCreditsMessage;

  /// No description provided for @lowCreditsMessage.
  ///
  /// In en, this message translates to:
  /// **'Your balance is running low. You can still accept rides, but consider contacting admin to top up soon.'**
  String get lowCreditsMessage;

  /// No description provided for @sufficientCreditsMessage.
  ///
  /// In en, this message translates to:
  /// **'You have enough credits to go online and accept rides.'**
  String get sufficientCreditsMessage;

  /// No description provided for @howCreditsWork.
  ///
  /// In en, this message translates to:
  /// **'How credits work'**
  String get howCreditsWork;

  /// No description provided for @creditInfo1.
  ///
  /// In en, this message translates to:
  /// **'1 credit is deducted each time you accept a ride'**
  String get creditInfo1;

  /// No description provided for @creditInfo2.
  ///
  /// In en, this message translates to:
  /// **'You need at least 1 credit to go online'**
  String get creditInfo2;

  /// No description provided for @creditInfo3.
  ///
  /// In en, this message translates to:
  /// **'Credits are granted by admin — contact them to top up'**
  String get creditInfo3;

  /// No description provided for @creditsChip.
  ///
  /// In en, this message translates to:
  /// **'Credits: {balance}'**
  String creditsChip(int balance);

  /// No description provided for @driverSettingsTitle.
  ///
  /// In en, this message translates to:
  /// **'Driver Settings'**
  String get driverSettingsTitle;

  /// No description provided for @riderSettingsTitle.
  ///
  /// In en, this message translates to:
  /// **'Settings'**
  String get riderSettingsTitle;

  /// No description provided for @languageSectionTitle.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get languageSectionTitle;

  /// No description provided for @languageSectionDesc.
  ///
  /// In en, this message translates to:
  /// **'App display language'**
  String get languageSectionDesc;

  /// No description provided for @languageEnglish.
  ///
  /// In en, this message translates to:
  /// **'English'**
  String get languageEnglish;

  /// No description provided for @languageIndonesian.
  ///
  /// In en, this message translates to:
  /// **'Bahasa Indonesia'**
  String get languageIndonesian;

  /// No description provided for @maxPickupRadiusTitle.
  ///
  /// In en, this message translates to:
  /// **'Max Pickup Radius'**
  String get maxPickupRadiusTitle;

  /// No description provided for @maxPickupRadiusDesc.
  ///
  /// In en, this message translates to:
  /// **'Only receive ride requests where the pickup is within this distance from your current location.'**
  String get maxPickupRadiusDesc;

  /// No description provided for @failedToLoadSettings.
  ///
  /// In en, this message translates to:
  /// **'Failed to load settings'**
  String get failedToLoadSettings;

  /// No description provided for @failedToSaveSettings.
  ///
  /// In en, this message translates to:
  /// **'Failed to save settings. Please try again.'**
  String get failedToSaveSettings;

  /// No description provided for @settingsSavedSuccess.
  ///
  /// In en, this message translates to:
  /// **'Settings saved successfully'**
  String get settingsSavedSuccess;

  /// No description provided for @saveSettingsButton.
  ///
  /// In en, this message translates to:
  /// **'Save Settings'**
  String get saveSettingsButton;

  /// No description provided for @driverVerificationTitle.
  ///
  /// In en, this message translates to:
  /// **'Driver Verification'**
  String get driverVerificationTitle;

  /// No description provided for @studentInfoPageTitle.
  ///
  /// In en, this message translates to:
  /// **'Student Information'**
  String get studentInfoPageTitle;

  /// No description provided for @studentInfoPageSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Enter your student details for verification'**
  String get studentInfoPageSubtitle;

  /// No description provided for @vehicleInfoPageTitle.
  ///
  /// In en, this message translates to:
  /// **'Vehicle Information'**
  String get vehicleInfoPageTitle;

  /// No description provided for @vehicleInfoPageSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Enter your vehicle details'**
  String get vehicleInfoPageSubtitle;

  /// No description provided for @ktmPhotoPageTitle.
  ///
  /// In en, this message translates to:
  /// **'Upload KTM Photo'**
  String get ktmPhotoPageTitle;

  /// No description provided for @ktmPhotoPageSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Take a clear photo of your student ID card'**
  String get ktmPhotoPageSubtitle;

  /// No description provided for @studentEmailLabel.
  ///
  /// In en, this message translates to:
  /// **'Student Email *'**
  String get studentEmailLabel;

  /// No description provided for @studentIdLabel.
  ///
  /// In en, this message translates to:
  /// **'Student ID *'**
  String get studentIdLabel;

  /// No description provided for @fullNameLabel.
  ///
  /// In en, this message translates to:
  /// **'Full Name (as on KTM) *'**
  String get fullNameLabel;

  /// No description provided for @whatsappNumberLabel.
  ///
  /// In en, this message translates to:
  /// **'WhatsApp Number *'**
  String get whatsappNumberLabel;

  /// No description provided for @whatsappNumberHint.
  ///
  /// In en, this message translates to:
  /// **'e.g., 08123456789'**
  String get whatsappNumberHint;

  /// No description provided for @whatsappNumberHelper.
  ///
  /// In en, this message translates to:
  /// **'Riders will contact you via this number'**
  String get whatsappNumberHelper;

  /// No description provided for @validatorWhatsappRequired.
  ///
  /// In en, this message translates to:
  /// **'WhatsApp number is required'**
  String get validatorWhatsappRequired;

  /// No description provided for @validatorWhatsappInvalid.
  ///
  /// In en, this message translates to:
  /// **'Enter a valid phone number (e.g., 08123456789)'**
  String get validatorWhatsappInvalid;

  /// No description provided for @profilePhotoLabel.
  ///
  /// In en, this message translates to:
  /// **'Profile Photo'**
  String get profilePhotoLabel;

  /// No description provided for @profilePhotoRequired.
  ///
  /// In en, this message translates to:
  /// **'Please upload your profile photo'**
  String get profilePhotoRequired;

  /// No description provided for @profilePhotoHelper.
  ///
  /// In en, this message translates to:
  /// **'Clear photo of your face for identity verification'**
  String get profilePhotoHelper;

  /// No description provided for @ktmPhotoLabel.
  ///
  /// In en, this message translates to:
  /// **'KTM Photo'**
  String get ktmPhotoLabel;

  /// No description provided for @vehicleTypeLabel.
  ///
  /// In en, this message translates to:
  /// **'Vehicle Type *'**
  String get vehicleTypeLabel;

  /// No description provided for @motorcycleOption.
  ///
  /// In en, this message translates to:
  /// **'Motorcycle'**
  String get motorcycleOption;

  /// No description provided for @carOption.
  ///
  /// In en, this message translates to:
  /// **'Car'**
  String get carOption;

  /// No description provided for @vehicleColorLabel.
  ///
  /// In en, this message translates to:
  /// **'Vehicle Color *'**
  String get vehicleColorLabel;

  /// No description provided for @vehicleTypeNotSupportedTitle.
  ///
  /// In en, this message translates to:
  /// **'Vehicle Type Not Supported'**
  String get vehicleTypeNotSupportedTitle;

  /// No description provided for @vehicleTypeNotSupportedMessage.
  ///
  /// In en, this message translates to:
  /// **'Sorry, we currently only support motorcycles. Car support will be available soon!'**
  String get vehicleTypeNotSupportedMessage;

  /// No description provided for @takePhotoButton.
  ///
  /// In en, this message translates to:
  /// **'Take Photo'**
  String get takePhotoButton;

  /// No description provided for @fromGalleryButton.
  ///
  /// In en, this message translates to:
  /// **'From Gallery'**
  String get fromGalleryButton;

  /// No description provided for @pleaseUploadKtm.
  ///
  /// In en, this message translates to:
  /// **'Please upload your KTM photo'**
  String get pleaseUploadKtm;

  /// No description provided for @pleaseSelectColor.
  ///
  /// In en, this message translates to:
  /// **'Please select a vehicle color'**
  String get pleaseSelectColor;

  /// No description provided for @failedToPickImage.
  ///
  /// In en, this message translates to:
  /// **'Failed to pick image: {error}'**
  String failedToPickImage(String error);

  /// No description provided for @checkingAvailability.
  ///
  /// In en, this message translates to:
  /// **'Checking availability...'**
  String get checkingAvailability;

  /// No description provided for @emailAvailable.
  ///
  /// In en, this message translates to:
  /// **'✓ Email is available'**
  String get emailAvailable;

  /// No description provided for @emailUnavailable.
  ///
  /// In en, this message translates to:
  /// **'✗ This email is already registered'**
  String get emailUnavailable;

  /// No description provided for @emailInvalidDomain.
  ///
  /// In en, this message translates to:
  /// **'✗ Email must be from an academic institution (*.ac.id)'**
  String get emailInvalidDomain;

  /// No description provided for @emailCheckError.
  ///
  /// In en, this message translates to:
  /// **'Could not check availability'**
  String get emailCheckError;

  /// No description provided for @usingGooglePhoto.
  ///
  /// In en, this message translates to:
  /// **'Using your Google profile photo'**
  String get usingGooglePhoto;

  /// No description provided for @pleaseAgreeToTerms.
  ///
  /// In en, this message translates to:
  /// **'Please agree to the terms before submitting'**
  String get pleaseAgreeToTerms;

  /// No description provided for @kycAgreementLabel.
  ///
  /// In en, this message translates to:
  /// **'I agree to operate as a Driver in the Tembalang, Semarang area and its surroundings.'**
  String get kycAgreementLabel;

  /// No description provided for @kycRejectedBannerTitle.
  ///
  /// In en, this message translates to:
  /// **'KYC Rejected'**
  String get kycRejectedBannerTitle;

  /// No description provided for @draftDataLoaded.
  ///
  /// In en, this message translates to:
  /// **'Draft data loaded (saved {timeAgo})'**
  String draftDataLoaded(String timeAgo);

  /// No description provided for @noPhotoSelected.
  ///
  /// In en, this message translates to:
  /// **'No photo selected'**
  String get noPhotoSelected;

  /// No description provided for @ktmPhotoInfo.
  ///
  /// In en, this message translates to:
  /// **'Make sure your KTM photo is clear and all details are visible'**
  String get ktmPhotoInfo;

  /// No description provided for @emailVerificationTitle.
  ///
  /// In en, this message translates to:
  /// **'Email Verification'**
  String get emailVerificationTitle;

  /// No description provided for @verifyYourEmailHeading.
  ///
  /// In en, this message translates to:
  /// **'Verify Your Email'**
  String get verifyYourEmailHeading;

  /// No description provided for @verificationCodeSentTo.
  ///
  /// In en, this message translates to:
  /// **'We’ve sent a 6-digit verification code to'**
  String get verificationCodeSentTo;

  /// No description provided for @verifyEmailButton.
  ///
  /// In en, this message translates to:
  /// **'Verify Email'**
  String get verifyEmailButton;

  /// No description provided for @pleaseEnterCode.
  ///
  /// In en, this message translates to:
  /// **'Please enter the 6-digit code'**
  String get pleaseEnterCode;

  /// No description provided for @didntReceiveCode.
  ///
  /// In en, this message translates to:
  /// **'Didn’t receive the code? '**
  String get didntReceiveCode;

  /// No description provided for @resendCountdown.
  ///
  /// In en, this message translates to:
  /// **'Resend in {seconds}s'**
  String resendCountdown(int seconds);

  /// No description provided for @resendCode.
  ///
  /// In en, this message translates to:
  /// **'Resend Code'**
  String get resendCode;

  /// No description provided for @verificationCodeExpiry.
  ///
  /// In en, this message translates to:
  /// **'The verification code expires in 10 minutes. Please check your spam folder if you don’t see the email.'**
  String get verificationCodeExpiry;

  /// No description provided for @newRideRequestTitle.
  ///
  /// In en, this message translates to:
  /// **'New Ride Request'**
  String get newRideRequestTitle;

  /// No description provided for @acceptWithinLabel.
  ///
  /// In en, this message translates to:
  /// **'Accept within'**
  String get acceptWithinLabel;

  /// No description provided for @requestTimedOut.
  ///
  /// In en, this message translates to:
  /// **'Request timed out'**
  String get requestTimedOut;

  /// No description provided for @rideAccepted.
  ///
  /// In en, this message translates to:
  /// **'Ride accepted!'**
  String get rideAccepted;

  /// No description provided for @rideDeclined.
  ///
  /// In en, this message translates to:
  /// **'Ride declined'**
  String get rideDeclined;

  /// No description provided for @rideCancelledByRider.
  ///
  /// In en, this message translates to:
  /// **'Ride request was cancelled'**
  String get rideCancelledByRider;

  /// No description provided for @errorRideCancelledByRider.
  ///
  /// In en, this message translates to:
  /// **'This ride request was cancelled by the rider'**
  String get errorRideCancelledByRider;

  /// No description provided for @errorRideAlreadyAccepted.
  ///
  /// In en, this message translates to:
  /// **'This ride was already accepted by another driver'**
  String get errorRideAlreadyAccepted;

  /// No description provided for @errorInsufficientCredits.
  ///
  /// In en, this message translates to:
  /// **'Insufficient credits to accept this ride'**
  String get errorInsufficientCredits;

  /// No description provided for @errorAlreadyActiveRide.
  ///
  /// In en, this message translates to:
  /// **'You already have an active ride'**
  String get errorAlreadyActiveRide;

  /// No description provided for @errorRideNoLongerAvailable.
  ///
  /// In en, this message translates to:
  /// **'Ride request no longer available'**
  String get errorRideNoLongerAvailable;

  /// No description provided for @errorFailedToAccept.
  ///
  /// In en, this message translates to:
  /// **'Failed to accept ride. Please try again.'**
  String get errorFailedToAccept;

  /// No description provided for @destinationLabel.
  ///
  /// In en, this message translates to:
  /// **'Destination'**
  String get destinationLabel;

  /// No description provided for @passengerSingular.
  ///
  /// In en, this message translates to:
  /// **'Passenger'**
  String get passengerSingular;

  /// No description provided for @passengerPlural.
  ///
  /// In en, this message translates to:
  /// **'Passengers'**
  String get passengerPlural;

  /// No description provided for @specialRequestsTitle.
  ///
  /// In en, this message translates to:
  /// **'Special Requests'**
  String get specialRequestsTitle;

  /// No description provided for @acceptRideButton.
  ///
  /// In en, this message translates to:
  /// **'Accept Ride'**
  String get acceptRideButton;

  /// No description provided for @declineButton.
  ///
  /// In en, this message translates to:
  /// **'Decline'**
  String get declineButton;

  /// No description provided for @acceptingRide.
  ///
  /// In en, this message translates to:
  /// **'Accepting ride...'**
  String get acceptingRide;

  /// No description provided for @locationPermissionTitle.
  ///
  /// In en, this message translates to:
  /// **'Location Permission Required'**
  String get locationPermissionTitle;

  /// No description provided for @locationPermissionMessage.
  ///
  /// In en, this message translates to:
  /// **'This app needs location permission to track your ride and update your position. Please enable location access in Settings.'**
  String get locationPermissionMessage;

  /// No description provided for @rideCompletedSnackbar.
  ///
  /// In en, this message translates to:
  /// **'Ride completed! 🎉'**
  String get rideCompletedSnackbar;

  /// No description provided for @rideCancelledSnackbar.
  ///
  /// In en, this message translates to:
  /// **'Ride cancelled'**
  String get rideCancelledSnackbar;

  /// No description provided for @rideCancelledByRiderSnackbar.
  ///
  /// In en, this message translates to:
  /// **'Ride was cancelled by the rider'**
  String get rideCancelledByRiderSnackbar;

  /// No description provided for @activeRideTitle.
  ///
  /// In en, this message translates to:
  /// **'Active Ride'**
  String get activeRideTitle;

  /// No description provided for @goBackButton.
  ///
  /// In en, this message translates to:
  /// **'Go Back'**
  String get goBackButton;

  /// No description provided for @failedToLoadRide.
  ///
  /// In en, this message translates to:
  /// **'Failed to load ride'**
  String get failedToLoadRide;

  /// No description provided for @statusUpdatedTo.
  ///
  /// In en, this message translates to:
  /// **'Status updated to {status}'**
  String statusUpdatedTo(String status);

  /// No description provided for @failedToUpdateStatus.
  ///
  /// In en, this message translates to:
  /// **'Failed to update status: {error}'**
  String failedToUpdateStatus(String error);

  /// No description provided for @markAsArrivedFab.
  ///
  /// In en, this message translates to:
  /// **'Mark as Arrived'**
  String get markAsArrivedFab;

  /// No description provided for @startRideFab.
  ///
  /// In en, this message translates to:
  /// **'Start Ride'**
  String get startRideFab;

  /// No description provided for @completeRideFab.
  ///
  /// In en, this message translates to:
  /// **'Complete Ride'**
  String get completeRideFab;

  /// No description provided for @cancelRideActiveTitle.
  ///
  /// In en, this message translates to:
  /// **'Cancel Ride'**
  String get cancelRideActiveTitle;

  /// No description provided for @cancelRideActiveConfirmMessage.
  ///
  /// In en, this message translates to:
  /// **'Are you sure you want to cancel this ride? This may affect your driver rating.'**
  String get cancelRideActiveConfirmMessage;

  /// No description provided for @noContinueButton.
  ///
  /// In en, this message translates to:
  /// **'No, Continue'**
  String get noContinueButton;

  /// No description provided for @yesCancelRide.
  ///
  /// In en, this message translates to:
  /// **'Yes, Cancel'**
  String get yesCancelRide;

  /// No description provided for @adminOverrideBanner.
  ///
  /// In en, this message translates to:
  /// **'Admin Override: {reason}'**
  String adminOverrideBanner(String reason);

  /// No description provided for @driverStatusDrivingToPickup.
  ///
  /// In en, this message translates to:
  /// **'Driving to pickup'**
  String get driverStatusDrivingToPickup;

  /// No description provided for @driverStatusArrivedAtPickup.
  ///
  /// In en, this message translates to:
  /// **'Arrived at pickup'**
  String get driverStatusArrivedAtPickup;

  /// No description provided for @driverStatusRideInProgress.
  ///
  /// In en, this message translates to:
  /// **'Ride in progress'**
  String get driverStatusRideInProgress;

  /// No description provided for @driverStatusRideCompleted.
  ///
  /// In en, this message translates to:
  /// **'Ride completed'**
  String get driverStatusRideCompleted;

  /// No description provided for @driverStatusRideCancelled.
  ///
  /// In en, this message translates to:
  /// **'Ride cancelled'**
  String get driverStatusRideCancelled;

  /// No description provided for @logoutTooltip.
  ///
  /// In en, this message translates to:
  /// **'Logout'**
  String get logoutTooltip;

  /// No description provided for @cancelRideTooltip.
  ///
  /// In en, this message translates to:
  /// **'Cancel ride'**
  String get cancelRideTooltip;

  /// No description provided for @statusOnlineLabel.
  ///
  /// In en, this message translates to:
  /// **'ONLINE'**
  String get statusOnlineLabel;

  /// No description provided for @statusOfflineLabel.
  ///
  /// In en, this message translates to:
  /// **'OFFLINE'**
  String get statusOfflineLabel;

  /// No description provided for @riderFallback.
  ///
  /// In en, this message translates to:
  /// **'Rider'**
  String get riderFallback;

  /// No description provided for @radiusCurrentLabel.
  ///
  /// In en, this message translates to:
  /// **'{radius} km radius'**
  String radiusCurrentLabel(String radius);

  /// No description provided for @radiusMinLabel.
  ///
  /// In en, this message translates to:
  /// **'0.5 km'**
  String get radiusMinLabel;

  /// No description provided for @radiusMaxLabel.
  ///
  /// In en, this message translates to:
  /// **'5 km'**
  String get radiusMaxLabel;

  /// No description provided for @noMaxRadiusLabel.
  ///
  /// In en, this message translates to:
  /// **'No max pickup radius'**
  String get noMaxRadiusLabel;

  /// No description provided for @noMaxRadiusDesc.
  ///
  /// In en, this message translates to:
  /// **'Accept all ride requests regardless of distance'**
  String get noMaxRadiusDesc;

  /// No description provided for @recenterMap.
  ///
  /// In en, this message translates to:
  /// **'Recenter map'**
  String get recenterMap;

  /// No description provided for @dateLabel.
  ///
  /// In en, this message translates to:
  /// **'Date'**
  String get dateLabel;

  /// No description provided for @driverLabel.
  ///
  /// In en, this message translates to:
  /// **'Driver'**
  String get driverLabel;

  /// No description provided for @maxPassengersHint.
  ///
  /// In en, this message translates to:
  /// **'Max: 4 passengers'**
  String get maxPassengersHint;

  /// No description provided for @specialRequestsPlaceholder.
  ///
  /// In en, this message translates to:
  /// **'e.g., \"Please wait at gate 2\"'**
  String get specialRequestsPlaceholder;

  /// No description provided for @failedGeneric.
  ///
  /// In en, this message translates to:
  /// **'Failed: {error}'**
  String failedGeneric(String error);

  /// No description provided for @validatorStudentEmailRequired.
  ///
  /// In en, this message translates to:
  /// **'Student email is required'**
  String get validatorStudentEmailRequired;

  /// No description provided for @validatorInvalidEmailFormat.
  ///
  /// In en, this message translates to:
  /// **'Invalid email format'**
  String get validatorInvalidEmailFormat;

  /// No description provided for @validatorEmailDomainInvalid.
  ///
  /// In en, this message translates to:
  /// **'Email must be from an academic institution (*.ac.id)'**
  String get validatorEmailDomainInvalid;

  /// No description provided for @validatorEmailAlreadyRegistered.
  ///
  /// In en, this message translates to:
  /// **'This email is already registered'**
  String get validatorEmailAlreadyRegistered;

  /// No description provided for @validatorStudentIdRequired.
  ///
  /// In en, this message translates to:
  /// **'Student ID is required'**
  String get validatorStudentIdRequired;

  /// No description provided for @validatorFullNameRequired.
  ///
  /// In en, this message translates to:
  /// **'Full name is required'**
  String get validatorFullNameRequired;

  /// No description provided for @validatorRequired.
  ///
  /// In en, this message translates to:
  /// **'Required'**
  String get validatorRequired;

  /// No description provided for @validatorVehicleColorRequired.
  ///
  /// In en, this message translates to:
  /// **'Vehicle color is required'**
  String get validatorVehicleColorRequired;

  /// No description provided for @studentEmailHint.
  ///
  /// In en, this message translates to:
  /// **'your.email@university.ac.id'**
  String get studentEmailHint;

  /// No description provided for @studentEmailHelper.
  ///
  /// In en, this message translates to:
  /// **'Must be your university email'**
  String get studentEmailHelper;

  /// No description provided for @studentIdHint.
  ///
  /// In en, this message translates to:
  /// **'e.g., 24010123140147'**
  String get studentIdHint;

  /// No description provided for @fullNameHint.
  ///
  /// In en, this message translates to:
  /// **'Your full name'**
  String get fullNameHint;

  /// No description provided for @selectColorHint.
  ///
  /// In en, this message translates to:
  /// **'Select vehicle color'**
  String get selectColorHint;

  /// No description provided for @licensePlateLabel.
  ///
  /// In en, this message translates to:
  /// **'License Plate *'**
  String get licensePlateLabel;

  /// No description provided for @licensePlateFormat.
  ///
  /// In en, this message translates to:
  /// **'Format: XX - 1234 - XXX'**
  String get licensePlateFormat;

  /// No description provided for @motorcycleOnlyNote.
  ///
  /// In en, this message translates to:
  /// **'Motorcycle rides — maximum 1 passenger'**
  String get motorcycleOnlyNote;

  /// No description provided for @cashPaymentNote.
  ///
  /// In en, this message translates to:
  /// **'Prepare {amount} in cash, pay the driver directly.'**
  String cashPaymentNote(String amount);

  /// No description provided for @altPaymentNote.
  ///
  /// In en, this message translates to:
  /// **'Other payment methods (QRIS, e-wallet) depend on driver availability.'**
  String get altPaymentNote;

  /// No description provided for @profileSectionTitle.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get profileSectionTitle;

  /// No description provided for @personalInfoSectionTitle.
  ///
  /// In en, this message translates to:
  /// **'Personal Information'**
  String get personalInfoSectionTitle;

  /// No description provided for @nameLabel.
  ///
  /// In en, this message translates to:
  /// **'Name'**
  String get nameLabel;

  /// No description provided for @phoneNumberLabel.
  ///
  /// In en, this message translates to:
  /// **'WhatsApp Number'**
  String get phoneNumberLabel;

  /// No description provided for @phoneNumberHint.
  ///
  /// In en, this message translates to:
  /// **'e.g., 08123456789'**
  String get phoneNumberHint;

  /// No description provided for @emailReadOnlyHint.
  ///
  /// In en, this message translates to:
  /// **'From Google account'**
  String get emailReadOnlyHint;

  /// No description provided for @saveProfileButton.
  ///
  /// In en, this message translates to:
  /// **'Save Profile'**
  String get saveProfileButton;

  /// No description provided for @profileSavedSuccess.
  ///
  /// In en, this message translates to:
  /// **'Profile saved successfully'**
  String get profileSavedSuccess;

  /// No description provided for @failedToSaveProfile.
  ///
  /// In en, this message translates to:
  /// **'Failed to save profile. Please try again.'**
  String get failedToSaveProfile;

  /// No description provided for @changeProfilePicture.
  ///
  /// In en, this message translates to:
  /// **'Change Profile Picture'**
  String get changeProfilePicture;

  /// No description provided for @takePhotoOption.
  ///
  /// In en, this message translates to:
  /// **'Take Photo'**
  String get takePhotoOption;

  /// No description provided for @chooseFromGalleryOption.
  ///
  /// In en, this message translates to:
  /// **'Choose from Gallery'**
  String get chooseFromGalleryOption;

  /// No description provided for @uploadingAvatar.
  ///
  /// In en, this message translates to:
  /// **'Uploading photo...'**
  String get uploadingAvatar;

  /// No description provided for @avatarUpdatedSuccess.
  ///
  /// In en, this message translates to:
  /// **'Profile picture updated'**
  String get avatarUpdatedSuccess;

  /// No description provided for @failedToUpdateAvatar.
  ///
  /// In en, this message translates to:
  /// **'Failed to update profile picture'**
  String get failedToUpdateAvatar;

  /// No description provided for @vehicleInfoSectionTitle.
  ///
  /// In en, this message translates to:
  /// **'Vehicle Information'**
  String get vehicleInfoSectionTitle;

  /// No description provided for @plateNumberLabel.
  ///
  /// In en, this message translates to:
  /// **'Plate Number'**
  String get plateNumberLabel;

  /// No description provided for @colorLabel.
  ///
  /// In en, this message translates to:
  /// **'Color'**
  String get colorLabel;

  /// No description provided for @accountSectionTitle.
  ///
  /// In en, this message translates to:
  /// **'Account'**
  String get accountSectionTitle;

  /// No description provided for @deleteAccountTitle.
  ///
  /// In en, this message translates to:
  /// **'Delete Account'**
  String get deleteAccountTitle;

  /// No description provided for @deleteAccountConfirmTitle.
  ///
  /// In en, this message translates to:
  /// **'Delete Your Account?'**
  String get deleteAccountConfirmTitle;

  /// No description provided for @deleteAccountConfirmMessage.
  ///
  /// In en, this message translates to:
  /// **'This action is permanent. All your data will be deleted. Type DELETE to confirm.'**
  String get deleteAccountConfirmMessage;

  /// No description provided for @deleteAccountBothRoleWarning.
  ///
  /// In en, this message translates to:
  /// **'You are registered as both a rider and driver. Deleting your account will remove all data for both roles.'**
  String get deleteAccountBothRoleWarning;

  /// No description provided for @deleteAccountButton.
  ///
  /// In en, this message translates to:
  /// **'Delete Account'**
  String get deleteAccountButton;

  /// No description provided for @deleteAccountSuccess.
  ///
  /// In en, this message translates to:
  /// **'Account deleted successfully'**
  String get deleteAccountSuccess;

  /// No description provided for @failedToDeleteAccount.
  ///
  /// In en, this message translates to:
  /// **'Failed to delete account. Please try again.'**
  String get failedToDeleteAccount;

  /// No description provided for @typeDeleteToConfirm.
  ///
  /// In en, this message translates to:
  /// **'Type DELETE to confirm'**
  String get typeDeleteToConfirm;

  /// No description provided for @aboutSectionTitle.
  ///
  /// In en, this message translates to:
  /// **'About'**
  String get aboutSectionTitle;

  /// No description provided for @appVersionLabel.
  ///
  /// In en, this message translates to:
  /// **'App Version'**
  String get appVersionLabel;

  /// No description provided for @rideSettingsSectionTitle.
  ///
  /// In en, this message translates to:
  /// **'Ride Settings'**
  String get rideSettingsSectionTitle;

  /// No description provided for @phoneRequiredTitle.
  ///
  /// In en, this message translates to:
  /// **'WhatsApp number required'**
  String get phoneRequiredTitle;

  /// No description provided for @phoneRequiredMessage.
  ///
  /// In en, this message translates to:
  /// **'Please add your WhatsApp number in Settings before requesting a ride. Drivers will contact you via WhatsApp.'**
  String get phoneRequiredMessage;

  /// No description provided for @goToSettings.
  ///
  /// In en, this message translates to:
  /// **'Go to Settings'**
  String get goToSettings;

  /// No description provided for @openWhatsAppTitle.
  ///
  /// In en, this message translates to:
  /// **'Open WhatsApp'**
  String get openWhatsAppTitle;

  /// No description provided for @openWhatsAppMessage.
  ///
  /// In en, this message translates to:
  /// **'Chat with {name} on WhatsApp?'**
  String openWhatsAppMessage(String name);

  /// No description provided for @whatsAppUnavailable.
  ///
  /// In en, this message translates to:
  /// **'WhatsApp number not available for this user'**
  String get whatsAppUnavailable;

  /// No description provided for @noActiveDriversMessage.
  ///
  /// In en, this message translates to:
  /// **'No active drivers available right now.'**
  String get noActiveDriversMessage;

  /// No description provided for @waitOrCancelMessage.
  ///
  /// In en, this message translates to:
  /// **'You can wait or cancel — we won’t hold you.'**
  String get waitOrCancelMessage;

  /// No description provided for @noDriversAcceptedRound.
  ///
  /// In en, this message translates to:
  /// **'No nearby drivers accepted yet.'**
  String get noDriversAcceptedRound;

  /// No description provided for @retryingCountdown.
  ///
  /// In en, this message translates to:
  /// **'Still searching… new drivers may join. {seconds}s remaining.'**
  String retryingCountdown(int seconds);

  /// No description provided for @driverUnavailableTryingNext.
  ///
  /// In en, this message translates to:
  /// **'That driver’s unavailable — trying the next one'**
  String get driverUnavailableTryingNext;

  /// No description provided for @notifyingDriverMinutesAway.
  ///
  /// In en, this message translates to:
  /// **'Notifying a driver {minutes} minutes away…'**
  String notifyingDriverMinutesAway(int minutes);

  /// No description provided for @legendBeingNotified.
  ///
  /// In en, this message translates to:
  /// **'Being notified'**
  String get legendBeingNotified;

  /// No description provided for @legendActiveDriver.
  ///
  /// In en, this message translates to:
  /// **'Active driver'**
  String get legendActiveDriver;

  /// No description provided for @legendUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Unavailable'**
  String get legendUnavailable;

  /// No description provided for @whereToTitle.
  ///
  /// In en, this message translates to:
  /// **'Where to?'**
  String get whereToTitle;

  /// No description provided for @pickupFieldHint.
  ///
  /// In en, this message translates to:
  /// **'Pickup location'**
  String get pickupFieldHint;

  /// No description provided for @dropoffFieldHint.
  ///
  /// In en, this message translates to:
  /// **'Where are you going?'**
  String get dropoffFieldHint;

  /// No description provided for @recentDestinations.
  ///
  /// In en, this message translates to:
  /// **'Recent Destinations'**
  String get recentDestinations;

  /// No description provided for @noRecentDestinations.
  ///
  /// In en, this message translates to:
  /// **'No recent destinations'**
  String get noRecentDestinations;

  /// No description provided for @confirmPickup.
  ///
  /// In en, this message translates to:
  /// **'Confirm Pickup'**
  String get confirmPickup;

  /// No description provided for @confirmDropoff.
  ///
  /// In en, this message translates to:
  /// **'Confirm Drop-off'**
  String get confirmDropoff;

  /// No description provided for @adjustPinPickup.
  ///
  /// In en, this message translates to:
  /// **'Move the map to adjust pickup point'**
  String get adjustPinPickup;

  /// No description provided for @adjustPinDropoff.
  ///
  /// In en, this message translates to:
  /// **'Move the map to adjust drop-off point'**
  String get adjustPinDropoff;

  /// No description provided for @resolvingLocation.
  ///
  /// In en, this message translates to:
  /// **'Finding address...'**
  String get resolvingLocation;

  /// No description provided for @droppedPin.
  ///
  /// In en, this message translates to:
  /// **'Dropped Pin'**
  String get droppedPin;

  /// No description provided for @yourLocation.
  ///
  /// In en, this message translates to:
  /// **'Your Location'**
  String get yourLocation;

  /// No description provided for @pickThisLocation.
  ///
  /// In en, this message translates to:
  /// **'Pick this location?'**
  String get pickThisLocation;

  /// No description provided for @cooldownCountdown.
  ///
  /// In en, this message translates to:
  /// **'Try again in {seconds}s'**
  String cooldownCountdown(int seconds);

  /// No description provided for @dragToAdjustHint.
  ///
  /// In en, this message translates to:
  /// **'Drag to adjust'**
  String get dragToAdjustHint;

  /// No description provided for @requestCancelledSuccess.
  ///
  /// In en, this message translates to:
  /// **'Request cancelled'**
  String get requestCancelledSuccess;

  /// No description provided for @thankYouFeedback.
  ///
  /// In en, this message translates to:
  /// **'Thanks for your feedback!'**
  String get thankYouFeedback;

  /// No description provided for @invalidPhoneFormat.
  ///
  /// In en, this message translates to:
  /// **'Enter a valid phone number (e.g., 08123456789)'**
  String get invalidPhoneFormat;

  /// No description provided for @pickupDistanceKm.
  ///
  /// In en, this message translates to:
  /// **'{distance} km away'**
  String pickupDistanceKm(String distance);

  /// No description provided for @etaToPickup.
  ///
  /// In en, this message translates to:
  /// **'~{minutes} min to pickup'**
  String etaToPickup(int minutes);

  /// No description provided for @riderNoPhone.
  ///
  /// In en, this message translates to:
  /// **'Rider has no WhatsApp number'**
  String get riderNoPhone;

  /// No description provided for @errorUnexpectedRideState.
  ///
  /// In en, this message translates to:
  /// **'Ride is in an unexpected state. Please refresh.'**
  String get errorUnexpectedRideState;

  /// No description provided for @errorRideNotFound.
  ///
  /// In en, this message translates to:
  /// **'Ride not found or already completed.'**
  String get errorRideNotFound;

  /// No description provided for @errorPermissionDenied.
  ///
  /// In en, this message translates to:
  /// **'You don’t have permission for this action.'**
  String get errorPermissionDenied;

  /// No description provided for @requestTimeoutExpired.
  ///
  /// In en, this message translates to:
  /// **'Request timed out — passed to next driver'**
  String get requestTimeoutExpired;

  /// No description provided for @colorBlack.
  ///
  /// In en, this message translates to:
  /// **'Black'**
  String get colorBlack;

  /// No description provided for @colorWhite.
  ///
  /// In en, this message translates to:
  /// **'White'**
  String get colorWhite;

  /// No description provided for @colorSilver.
  ///
  /// In en, this message translates to:
  /// **'Silver'**
  String get colorSilver;

  /// No description provided for @colorGray.
  ///
  /// In en, this message translates to:
  /// **'Gray'**
  String get colorGray;

  /// No description provided for @colorRed.
  ///
  /// In en, this message translates to:
  /// **'Red'**
  String get colorRed;

  /// No description provided for @colorBlue.
  ///
  /// In en, this message translates to:
  /// **'Blue'**
  String get colorBlue;

  /// No description provided for @colorGreen.
  ///
  /// In en, this message translates to:
  /// **'Green'**
  String get colorGreen;

  /// No description provided for @colorYellow.
  ///
  /// In en, this message translates to:
  /// **'Yellow'**
  String get colorYellow;

  /// No description provided for @colorBrown.
  ///
  /// In en, this message translates to:
  /// **'Brown'**
  String get colorBrown;

  /// No description provided for @colorOrange.
  ///
  /// In en, this message translates to:
  /// **'Orange'**
  String get colorOrange;

  /// No description provided for @colorPurple.
  ///
  /// In en, this message translates to:
  /// **'Purple'**
  String get colorPurple;

  /// No description provided for @colorGold.
  ///
  /// In en, this message translates to:
  /// **'Gold'**
  String get colorGold;

  /// No description provided for @colorOther.
  ///
  /// In en, this message translates to:
  /// **'Other'**
  String get colorOther;

  /// No description provided for @notRatedYet.
  ///
  /// In en, this message translates to:
  /// **'Not rated'**
  String get notRatedYet;

  /// No description provided for @rateButton.
  ///
  /// In en, this message translates to:
  /// **'Rate'**
  String get rateButton;

  /// No description provided for @unratedRidesBubble.
  ///
  /// In en, this message translates to:
  /// **'You haven’t rated your driver yet 👋'**
  String get unratedRidesBubble;

  /// No description provided for @currencyFormat.
  ///
  /// In en, this message translates to:
  /// **'Rp {amount}'**
  String currencyFormat(String amount);

  /// No description provided for @earningsHistoryTitle.
  ///
  /// In en, this message translates to:
  /// **'Earnings History'**
  String get earningsHistoryTitle;

  /// No description provided for @earningsToday.
  ///
  /// In en, this message translates to:
  /// **'Today'**
  String get earningsToday;

  /// No description provided for @earningsThisWeek.
  ///
  /// In en, this message translates to:
  /// **'This Week'**
  String get earningsThisWeek;

  /// No description provided for @earningsThisMonth.
  ///
  /// In en, this message translates to:
  /// **'This Month'**
  String get earningsThisMonth;

  /// No description provided for @noEarningsYet.
  ///
  /// In en, this message translates to:
  /// **'No earnings yet'**
  String get noEarningsYet;

  /// No description provided for @fareAmount.
  ///
  /// In en, this message translates to:
  /// **'Rp {amount}'**
  String fareAmount(String amount);

  /// No description provided for @rideHistoryButton.
  ///
  /// In en, this message translates to:
  /// **'Ride History'**
  String get rideHistoryButton;

  /// No description provided for @ratingHistoryTitle.
  ///
  /// In en, this message translates to:
  /// **'Rating History'**
  String get ratingHistoryTitle;

  /// No description provided for @overallRating.
  ///
  /// In en, this message translates to:
  /// **'Overall Rating'**
  String get overallRating;

  /// No description provided for @ratingsReceived.
  ///
  /// In en, this message translates to:
  /// **'{count} ratings received'**
  String ratingsReceived(int count);

  /// No description provided for @noRatingsYet.
  ///
  /// In en, this message translates to:
  /// **'No ratings yet'**
  String get noRatingsYet;

  /// No description provided for @kycStatusTitle.
  ///
  /// In en, this message translates to:
  /// **'Verification Status'**
  String get kycStatusTitle;

  /// No description provided for @kycVerified.
  ///
  /// In en, this message translates to:
  /// **'Verified'**
  String get kycVerified;

  /// No description provided for @kycPendingApproval.
  ///
  /// In en, this message translates to:
  /// **'Pending Approval'**
  String get kycPendingApproval;

  /// No description provided for @kycEmailPending.
  ///
  /// In en, this message translates to:
  /// **'Email Not Verified'**
  String get kycEmailPending;

  /// No description provided for @kycNotSubmitted.
  ///
  /// In en, this message translates to:
  /// **'Not Submitted'**
  String get kycNotSubmitted;

  /// No description provided for @kycSuspended.
  ///
  /// In en, this message translates to:
  /// **'Suspended'**
  String get kycSuspended;

  /// No description provided for @kycDetailsTitle.
  ///
  /// In en, this message translates to:
  /// **'Submitted Information'**
  String get kycDetailsTitle;

  /// No description provided for @kycStudentEmail.
  ///
  /// In en, this message translates to:
  /// **'Student Email'**
  String get kycStudentEmail;

  /// No description provided for @kycStudentId.
  ///
  /// In en, this message translates to:
  /// **'Student ID'**
  String get kycStudentId;

  /// No description provided for @kycStudentName.
  ///
  /// In en, this message translates to:
  /// **'Student Name'**
  String get kycStudentName;

  /// No description provided for @kycVehicleType.
  ///
  /// In en, this message translates to:
  /// **'Vehicle Type'**
  String get kycVehicleType;

  /// No description provided for @kycVehiclePlate.
  ///
  /// In en, this message translates to:
  /// **'Vehicle Plate'**
  String get kycVehiclePlate;

  /// No description provided for @kycVehicleColor.
  ///
  /// In en, this message translates to:
  /// **'Vehicle Color'**
  String get kycVehicleColor;

  /// No description provided for @kycKtmPhoto.
  ///
  /// In en, this message translates to:
  /// **'KTM Photo'**
  String get kycKtmPhoto;

  /// No description provided for @editKycButton.
  ///
  /// In en, this message translates to:
  /// **'Edit KYC Data'**
  String get editKycButton;

  /// No description provided for @editKycConfirmTitle.
  ///
  /// In en, this message translates to:
  /// **'Edit KYC Data?'**
  String get editKycConfirmTitle;

  /// No description provided for @editKycConfirmMessage.
  ///
  /// In en, this message translates to:
  /// **'Editing will send you back to the KYC flow and you will need to be re-verified. All current verification data (student ID, vehicle info, uploaded documents) will be deleted. Your ride history and ratings will be kept.\n\nType WARJO to confirm.'**
  String get editKycConfirmMessage;

  /// No description provided for @editKycBothRoleWarning.
  ///
  /// In en, this message translates to:
  /// **'You are also registered as a rider. Editing your KYC will clear your profile photo and phone number for both roles.'**
  String get editKycBothRoleWarning;

  /// No description provided for @editKycSuccess.
  ///
  /// In en, this message translates to:
  /// **'KYC data cleared — please re-submit'**
  String get editKycSuccess;

  /// No description provided for @failedToEditKyc.
  ///
  /// In en, this message translates to:
  /// **'Failed to edit KYC data'**
  String get failedToEditKyc;

  /// No description provided for @typeWarjoToConfirm.
  ///
  /// In en, this message translates to:
  /// **'Type WARJO to confirm'**
  String get typeWarjoToConfirm;

  /// No description provided for @mustBeOfflineToEdit.
  ///
  /// In en, this message translates to:
  /// **'You must go offline before editing KYC data'**
  String get mustBeOfflineToEdit;

  /// No description provided for @noKycData.
  ///
  /// In en, this message translates to:
  /// **'No verification data submitted yet'**
  String get noKycData;

  /// No description provided for @kickedStaleGps.
  ///
  /// In en, this message translates to:
  /// **'You were taken offline because your GPS signal was lost. Please check your location settings.'**
  String get kickedStaleGps;

  /// No description provided for @kickedZeroCredits.
  ///
  /// In en, this message translates to:
  /// **'You were taken offline because your credit balance reached zero. Contact admin to top up.'**
  String get kickedZeroCredits;

  /// No description provided for @kickedGeneric.
  ///
  /// In en, this message translates to:
  /// **'You were taken offline by the system.'**
  String get kickedGeneric;

  /// No description provided for @forceUpdateTitle.
  ///
  /// In en, this message translates to:
  /// **'Update Required'**
  String get forceUpdateTitle;

  /// No description provided for @forceUpdateMessage.
  ///
  /// In en, this message translates to:
  /// **'Your app version is no longer supported. Please update to the latest version.'**
  String get forceUpdateMessage;

  /// No description provided for @forceUpdateButton.
  ///
  /// In en, this message translates to:
  /// **'Update Now'**
  String get forceUpdateButton;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['en', 'id'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'en':
      return AppLocalizationsEn();
    case 'id':
      return AppLocalizationsId();
  }

  throw FlutterError(
      'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
      'an issue with the localizations generation tool. Please file an issue '
      'on GitHub with a reproducible sample app and the gen-l10n configuration '
      'that was used.');
}
