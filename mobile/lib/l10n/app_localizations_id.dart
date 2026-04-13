// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Indonesian (`id`).
class AppLocalizationsId extends AppLocalizations {
  AppLocalizationsId([String locale = 'id']) : super(locale);

  @override
  String get cancel => 'Cancel';

  @override
  String get yes => 'Ya';

  @override
  String get no => 'Tidak';

  @override
  String get ok => 'OK';

  @override
  String get retry => 'Coba Lagi';

  @override
  String get close => 'Tutup';

  @override
  String get back => 'Kembali';

  @override
  String get continueButton => 'Lanjutkan';

  @override
  String get loading => 'Loading...';

  @override
  String get saving => 'Menyimpan...';

  @override
  String get cancelling => 'Membatalkan...';

  @override
  String get submit => 'Kirim';

  @override
  String get next => 'Selanjutnya';

  @override
  String get skipForNow => 'Lewati dulu';

  @override
  String get gotIt => 'Mengerti!';

  @override
  String get openSettings => 'Buka Pengaturan';

  @override
  String get dismiss => 'Tutup';

  @override
  String get driverFallback => 'Pengemudi';

  @override
  String get yourDriver => 'Pengemudi Anda';

  @override
  String get riderTagline => 'Antar Jemput Anda untuk kapan saja';

  @override
  String get driverTagline => 'Berkendara dan berpenghasilan di kampus';

  @override
  String get signInWithGoogle => 'Masuk dengan Google';

  @override
  String get signingIn => 'Masuk...';

  @override
  String get termsNoticePrefix => 'Dengan masuk, Anda menyetujui ';

  @override
  String get termsAndConditions => 'Syarat dan Ketentuan';

  @override
  String get termsNoticeConnector => ' dan ';

  @override
  String get privacyPolicy => 'Kebijakan Privasi';

  @override
  String get termsNoticeSuffix => ' kami.';

  @override
  String get continueSessionTitle => 'Lanjutkan Sesi Sebelumnya?';

  @override
  String get continueRideMessage =>
      'Anda memiliki anjem aktif. Ingin melanjutkan?';

  @override
  String get continuePendingMessage =>
      'Anda memiliki permintaan anjem yang tertunda. Ingin melanjutkan?';

  @override
  String get logoutTitle => 'Keluar';

  @override
  String get logoutConfirmMessage => 'Apakah Anda yakin ingin keluar?';

  @override
  String get accountSuspendedBanner =>
      'Akun Anda telah diblokir. Anda tidak dapat memesan anjem.';

  @override
  String get activeRideRequestBanner => 'Anda memiliki permintaan anjem aktif';

  @override
  String get viewButton => 'Lihat';

  @override
  String get oneMoreCancellationWarning =>
      'Peringatan: Satu pembatalan lagi akan memblokir akun Anda.';

  @override
  String get repeatedCancellationsWarning =>
      'Peringatan: Pembatalan berulang dapat mengakibatkan blokir sementara.';

  @override
  String get accountSuspendedButton => 'Akun diblokir';

  @override
  String get requestInProgressButton => 'Permintaan Sedang Diproses';

  @override
  String get requestRideButton => 'Pesan Anjem';

  @override
  String get selectLocationsTitle => 'Pilih Lokasi';

  @override
  String get searchLocationsHint => 'Cari lokasi (Rusunawa Undip...)';

  @override
  String get pickupLabel => 'Jemput disini';

  @override
  String get dropOffLabel => 'Antar kesini';

  @override
  String get searchingText => 'Mencari...';

  @override
  String get startTypingHint => 'Mulai ketik untuk mencari lokasi';

  @override
  String get noResultsFound => 'Tidak ada hasil';

  @override
  String get locationResolveFailed =>
      'Tidak dapat menentukan salah satu lokasi yang dipilih. Silakan cari lagi.';

  @override
  String estimateFailed(String error) {
    return 'Gagal mendapatkan estimasi: $error';
  }

  @override
  String get beaconLabel => 'Titik';

  @override
  String get cancelRequestTitle => 'Batalkan Permintaan?';

  @override
  String get cancelRequestConfirmMessage =>
      'Apakah Anda yakin ingin membatalkan permintaan anjem ini?';

  @override
  String get yesCancelButton => 'Ya, Batalkan';

  @override
  String get findingDriverTitle => 'Mencari Pengemudi';

  @override
  String get noDriversAvailable => 'Tidak ada pengemudi tersedia saat ini';

  @override
  String get findingDriverMessage => 'Sedang mencari pengemudi untuk Anda...';

  @override
  String retryingInSeconds(int seconds) {
    return 'Sedang mencoba ulang... tersisa $seconds detik…';
  }

  @override
  String queuePosition(String position) {
    return 'Posisi Antrian: $position';
  }

  @override
  String get pleaseWaitMessage =>
      'Harap tunggu sementara kami mencarikan Anda pengemudi terdekat';

  @override
  String get cancelRequestButton => 'Batalkan Permintaan';

  @override
  String get driverFoundTitle => 'Pengemudi Ditemukan!';

  @override
  String driverOnTheWayMessage(String driverName) {
    return '$driverName sedang dalam perjalanan';
  }

  @override
  String get callingDriver => 'Menghubungi pengemudi...';

  @override
  String get callingRider => 'Menghubungi penumpang...';

  @override
  String get rideCancelledTitle => 'Anjem Dibatalkan';

  @override
  String get driverCancelledMessage => 'Pengemudi Anda membatalkan anjem ini.';

  @override
  String adminCancelReason(String reason) {
    return 'Alasan admin: $reason';
  }

  @override
  String get cancelRideTitle => 'Batalkan Anjem?';

  @override
  String get cancelRideConfirmMessage =>
      'Apakah Anda yakin ingin membatalkan? Ini mungkin mengakibatkan jeda singkat sebelum Anda dapat memesan lagi.';

  @override
  String cancelFailed(String error) {
    return 'Gagal membatalkan: $error';
  }

  @override
  String get statusDriverOnTheWay => 'Pengemudi dalam perjalanan';

  @override
  String get statusDriverArrived => 'Pengemudi telah tiba';

  @override
  String get statusRideInProgress => 'Anjem berlangsung';

  @override
  String get statusRideCompleted => 'Anjem selesai';

  @override
  String get statusRideCancelled => 'Anjem dibatalkan';

  @override
  String etaMinutes(String minutes) {
    return 'ETA: $minutes menit';
  }

  @override
  String get rateYourRideTitle => 'Rate Anjem Anda';

  @override
  String get rideCompletedHeading => 'Anjem Selesai!';

  @override
  String get fareLabel => 'Tarif:';

  @override
  String get fromLabel => 'Dari:';

  @override
  String get toLabel => 'Ke:';

  @override
  String get howWasYourRide => 'Bagaimana anjem Anda?';

  @override
  String get whatDidYouLike => 'Apa yang Anda sukai?';

  @override
  String get additionalFeedbackOptional => 'Masukan Tambahan (Opsional)';

  @override
  String get shareFeedbackHint => 'Bagikan pengalaman Anda...';

  @override
  String get submitRatingButton => 'Kirim Rating';

  @override
  String get tagCleanVehicle => 'Kendaraan Bersih';

  @override
  String get tagSafeDriving => 'Berkendara Aman';

  @override
  String get tagFriendlyDriver => 'Pengemudi Ramah';

  @override
  String get tagOnTime => 'Tepat Waktu';

  @override
  String get tagProfessional => 'Profesional';

  @override
  String get tagSmoothRide => 'Perjalanan Nyaman';

  @override
  String get tagHelpful => 'Membantu';

  @override
  String get rideHistoryTitle => 'Riwayat Anjem';

  @override
  String get filterAll => 'Semua';

  @override
  String get filterCompleted => 'Selesai';

  @override
  String get filterCancelled => 'Dibatalkan';

  @override
  String get noRidesYet => 'Belum ada anjem';

  @override
  String get rideDetailsTitle => 'Detail Anjem';

  @override
  String get estimatedFareLabel => 'Estimasi Tarif:';

  @override
  String get passengerCountTitle => 'Jumlah Penumpang';

  @override
  String get specialRequestsHint => 'Permintaan Khusus (Opsional)';

  @override
  String get confirmRequest => 'Konfirmasi Anjem';

  @override
  String welcomeDriver(String name) {
    return 'Selamat datang, $name!';
  }

  @override
  String get statusOnlineWithRide => 'Anda memiliki anjem aktif';

  @override
  String get statusOnlineIdle => 'Menunggu permintaan anjem...';

  @override
  String get statusOfflineMessage =>
      'Klik \"Mulai Anjem\" untuk mulai menerima permintaan anjem';

  @override
  String get joiningQueue => 'Bergabung ke antrian...';

  @override
  String get youreNextInQueue => 'Anda berikutnya dalam antrian!';

  @override
  String queuePositionNumber(int position) {
    return 'Posisi Antrian: #$position';
  }

  @override
  String get nextRideComing =>
      'Permintaan anjem berikutnya akan datang kepada Anda';

  @override
  String driversAheadOfYou(int count) {
    return '$count pengemudi di depan Anda';
  }

  @override
  String get todaysEarningsTitle => 'Penghasilan Hari Ini';

  @override
  String ridesCompletedToday(int count) {
    return '$count anjem selesai hari ini';
  }

  @override
  String get ratingLabel => 'Rating';

  @override
  String get totalRidesLabel => 'Total Anjem';

  @override
  String get statusLabel => 'Status';

  @override
  String get statusSuspended => 'diblokir';

  @override
  String get statusVerified => 'Terverifikasi';

  @override
  String get statusUnverified => 'Belum Terverifikasi';

  @override
  String get youHaveActiveRide => 'Anda memiliki anjem aktif';

  @override
  String get viewActiveRide => 'Lihat Anjem Aktif';

  @override
  String get accountSuspendedContactAdmin =>
      'Akun Anda telah diblokir. Hubungi admin.';

  @override
  String suspensionReason(String reason) {
    return 'Alasan: $reason';
  }

  @override
  String get pendingApprovalBanner =>
      'Akun Anda menunggu persetujuan admin. Anda tidak dapat online sebelum disetujui.';

  @override
  String get unableToLoadStats => 'Tidak dapat memuat statistik';

  @override
  String get noCreditsWarning =>
      'Anda tidak memiliki kredit. Hubungi admin untuk mengisi saldo sebelum online.';

  @override
  String lowCreditsWarning(int balance) {
    return 'Kredit rendah: $balance tersisa. Segera hubungi admin untuk mengisi saldo.';
  }

  @override
  String get earningsHistoryButton => 'Riwayat Penghasilan';

  @override
  String get settingsButton => 'Pengaturan';

  @override
  String get loadingProfile => 'Memuat Profil...';

  @override
  String get goOnlineButton => 'Mulai Anjem';

  @override
  String get activeRideButton => 'Anjem Aktif';

  @override
  String get goOfflineButton => 'Offline';

  @override
  String get topUpComingSoon => 'Isi Ulang — Segera Hadir';

  @override
  String get yourCreditsTitle => 'Kredit Anda';

  @override
  String get noCreditsBalance => 'Tidak ada kredit';

  @override
  String get lowCreditsBalance => 'Kredit rendah';

  @override
  String get sufficientCreditsBalance => 'Kredit cukup';

  @override
  String get noCreditsMessage =>
      'Anda tidak dapat online atau menerima anjem sampai saldo diisi ulang. Hubungi admin Anda.';

  @override
  String get lowCreditsMessage =>
      'Saldo Anda hampir habis. Anda masih bisa menerima anjem, tapi pertimbangkan menghubungi admin segera.';

  @override
  String get sufficientCreditsMessage =>
      'Anda memiliki cukup kredit untuk online dan menerima anjem.';

  @override
  String get howCreditsWork => 'Cara kerja Kredit';

  @override
  String get creditInfo1 =>
      '1 kredit dikurangi setiap kali Anda menerima anjem';

  @override
  String get creditInfo2 => 'Anda membutuhkan minimal 1 kredit untuk online';

  @override
  String get creditInfo3 =>
      'Kredit diberikan oleh admin — hubungi mereka untuk mengisi saldo';

  @override
  String creditsChip(int balance) {
    return 'Kredit: $balance';
  }

  @override
  String get driverSettingsTitle => 'Pengaturan Pengemudi';

  @override
  String get riderSettingsTitle => 'Pengaturan';

  @override
  String get languageSectionTitle => 'Bahasa';

  @override
  String get languageSectionDesc => 'Bahasa tampilan aplikasi';

  @override
  String get languageEnglish => 'Inggris';

  @override
  String get languageIndonesian => 'Bahasa Indonesia';

  @override
  String get maxPickupRadiusTitle => 'Radius Jemput Maksimal';

  @override
  String get maxPickupRadiusDesc =>
      'Hanya terima permintaan anjem dengan titik jemput dalam jarak ini dari lokasi Anda saat ini.';

  @override
  String get failedToLoadSettings => 'Gagal memuat pengaturan';

  @override
  String get failedToSaveSettings =>
      'Gagal menyimpan pengaturan. Silakan coba lagi.';

  @override
  String get settingsSavedSuccess => 'Pengaturan berhasil disimpan';

  @override
  String get saveSettingsButton => 'Simpan Pengaturan';

  @override
  String get driverVerificationTitle => 'Verifikasi Pengemudi';

  @override
  String get studentInfoPageTitle => 'Informasi Mahasiswa';

  @override
  String get studentInfoPageSubtitle =>
      'Masukkan data mahasiswa Anda untuk verifikasi';

  @override
  String get vehicleInfoPageTitle => 'Informasi Kendaraan';

  @override
  String get vehicleInfoPageSubtitle => 'Masukkan detail kendaraan Anda';

  @override
  String get ktmPhotoPageTitle => 'Upload Foto';

  @override
  String get ktmPhotoPageSubtitle => 'Ambil foto KTM Anda yang jelas';

  @override
  String get studentEmailLabel => 'Email Mahasiswa *';

  @override
  String get studentIdLabel => 'NIM *';

  @override
  String get fullNameLabel => 'Nama Lengkap (sesuai KTM) *';

  @override
  String get whatsappNumberLabel => 'Nomor WhatsApp *';

  @override
  String get whatsappNumberHint => 'contoh: 08123456789';

  @override
  String get whatsappNumberHelper =>
      'Penumpang akan menghubungi melalui nomor ini';

  @override
  String get validatorWhatsappRequired => 'Nomor WhatsApp wajib diisi';

  @override
  String get validatorWhatsappInvalid =>
      'Masukkan nomor telepon yang valid (contoh: 08123456789)';

  @override
  String get profilePhotoLabel => 'Foto Profil';

  @override
  String get profilePhotoRequired => 'Silakan unggah foto profil Anda';

  @override
  String get profilePhotoHelper =>
      'Foto wajah yang jelas untuk verifikasi identitas';

  @override
  String get ktmPhotoLabel => 'Foto KTM';

  @override
  String get vehicleTypeLabel => 'Jenis Kendaraan *';

  @override
  String get motorcycleOption => 'Motor';

  @override
  String get carOption => 'Mobil';

  @override
  String get vehicleColorLabel => 'Warna Kendaraan *';

  @override
  String get vehicleTypeNotSupportedTitle => 'Jenis Kendaraan Tidak Didukung';

  @override
  String get vehicleTypeNotSupportedMessage =>
      'Maaf, kami saat ini hanya mendukung motor. Dukungan mobil akan tersedia segera!';

  @override
  String get takePhotoButton => 'Ambil Foto';

  @override
  String get fromGalleryButton => 'Dari Galeri';

  @override
  String get pleaseUploadKtm => 'Harap upload foto KTM Anda';

  @override
  String get pleaseSelectColor => 'Harap pilih warna kendaraan';

  @override
  String failedToPickImage(String error) {
    return 'Gagal memilih gambar: $error';
  }

  @override
  String get checkingAvailability => 'Memeriksa ketersediaan...';

  @override
  String get emailAvailable => '✓ Email tersedia';

  @override
  String get emailUnavailable => '✗ Email ini sudah terdaftar';

  @override
  String get emailInvalidDomain =>
      '✗ Email harus dari institusi akademik (*.ac.id)';

  @override
  String get emailCheckError => 'Tidak dapat memeriksa ketersediaan';

  @override
  String get usingGooglePhoto => 'Menggunakan foto profil Google Anda';

  @override
  String get pleaseAgreeToTerms => 'Harap setujui ketentuan sebelum mengirim';

  @override
  String get kycAgreementLabel =>
      'Saya setuju untuk beroperasi sebagai Driver di area Tembalang, Semarang dan sekitarnya.';

  @override
  String get kycRejectedBannerTitle => 'KYC Ditolak';

  @override
  String draftDataLoaded(String timeAgo) {
    return 'Data draf dimuat (disimpan $timeAgo)';
  }

  @override
  String get noPhotoSelected => 'Belum ada foto';

  @override
  String get ktmPhotoInfo =>
      'Pastikan foto KTM Anda jelas dan semua detail terlihat';

  @override
  String get emailVerificationTitle => 'Verifikasi Email';

  @override
  String get verifyYourEmailHeading => 'Verifikasi Email Anda';

  @override
  String get verificationCodeSentTo =>
      'Kami telah mengirim kode verifikasi 6 digit ke';

  @override
  String get verifyEmailButton => 'Verifikasi Email';

  @override
  String get pleaseEnterCode => 'Harap masukkan kode 6 digit';

  @override
  String get didntReceiveCode => 'Tidak menerima kode? ';

  @override
  String resendCountdown(int seconds) {
    return 'Kirim ulang dalam $seconds detik';
  }

  @override
  String get resendCode => 'Kirim Ulang Kode';

  @override
  String get changeEmail => 'Ganti Email';

  @override
  String get changeEmailTitle => 'Ganti Alamat Email';

  @override
  String get newStudentEmailHint => 'email.baru@universitas.ac.id';

  @override
  String get invalidEmailDomain =>
      'Email harus dari institusi akademik (*.ac.id)';

  @override
  String get hourlyLimitReached =>
      'Terlalu banyak percobaan. Silakan coba lagi nanti.';

  @override
  String resendsRemaining(int count) {
    return '$count pengiriman ulang tersisa dalam satu jam';
  }

  @override
  String get verificationCodeExpiry =>
      'Kode verifikasi berlaku selama 10 menit. Periksa folder spam jika Anda tidak melihat emailnya.';

  @override
  String get newRideRequestTitle => 'Permintaan Anjem Baru';

  @override
  String get acceptWithinLabel => 'Terima dalam';

  @override
  String get requestTimedOut => 'Waktu permintaan habis';

  @override
  String get rideAccepted => 'Anjem diterima!';

  @override
  String get rideDeclined => 'Anjem ditolak';

  @override
  String get rideCancelledByRider => 'Permintaan anjem dibatalkan';

  @override
  String get errorRideCancelledByRider =>
      'Permintaan anjem ini dibatalkan oleh penumpang';

  @override
  String get errorRideAlreadyAccepted =>
      'Anjem ini sudah diterima oleh pengemudi lain';

  @override
  String get errorInsufficientCredits =>
      'Kredit tidak cukup untuk menerima anjem ini';

  @override
  String get errorAlreadyActiveRide => 'Anda sudah memiliki anjem aktif';

  @override
  String get errorRideNoLongerAvailable =>
      'Permintaan anjem tidak lagi tersedia';

  @override
  String get errorFailedToAccept => 'Gagal menerima anjem. Silakan coba lagi.';

  @override
  String get destinationLabel => 'Tujuan';

  @override
  String get passengerSingular => 'Penumpang';

  @override
  String get passengerPlural => 'Penumpang';

  @override
  String get specialRequestsTitle => 'Permintaan Khusus';

  @override
  String get acceptRideButton => 'Terima Anjem';

  @override
  String get declineButton => 'Tolak';

  @override
  String get acceptingRide => 'Menerima anjem...';

  @override
  String get locationPermissionTitle => 'Izin Lokasi Diperlukan';

  @override
  String get locationPermissionMessage =>
      'Aplikasi ini membutuhkan izin lokasi untuk melacak perjalanan dan memperbarui posisi Anda. Harap aktifkan akses lokasi di Pengaturan.';

  @override
  String get backgroundLocationTitle => 'Izinkan lokasi sepanjang waktu';

  @override
  String get backgroundLocationMessage =>
      'Anjem membutuhkan lokasi Anda meskipun aplikasi diminimalkan agar Anda tetap dalam antrian dan menerima permintaan anjem. Di layar berikutnya, pilih \"Izinkan sepanjang waktu\".';

  @override
  String get backgroundLocationContinue => 'Lanjutkan';

  @override
  String get backgroundLocationLater => 'Nanti saja';

  @override
  String get batteryOptimizationTitle => 'Tetap online di latar belakang';

  @override
  String get batteryOptimizationMessage =>
      'Anjem perlu terus melacak lokasi Anda saat menunggu permintaan anjem, bahkan saat aplikasi tidak dibuka. Mohon nonaktifkan optimasi baterai untuk Anjem di layar berikutnya.';

  @override
  String get batteryOptimizationContinue => 'Buka Pengaturan';

  @override
  String get batteryOptimizationLater => 'Nanti saja';

  @override
  String get rideCompletedSnackbar => 'Anjem selesai! 🎉';

  @override
  String get rideCancelledSnackbar => 'Anjem dibatalkan';

  @override
  String get rideCancelledByRiderSnackbar => 'Anjem dibatalkan oleh penumpang';

  @override
  String get activeRideTitle => 'Anjem Aktif';

  @override
  String get goBackButton => 'Kembali';

  @override
  String get failedToLoadRide => 'Gagal memuat anjem';

  @override
  String statusUpdatedTo(String status) {
    return 'Status diperbarui menjadi $status';
  }

  @override
  String failedToUpdateStatus(String error) {
    return 'Gagal memperbarui status: $error';
  }

  @override
  String get markAsArrivedFab => 'Tandai Sudah Tiba';

  @override
  String get startRideFab => 'Mulai Anjem';

  @override
  String get completeRideFab => 'Selesaikan Anjem';

  @override
  String get cancelRideActiveTitle => 'Batalkan Anjem';

  @override
  String get cancelRideActiveConfirmMessage =>
      'Apakah Anda yakin ingin membatalkan anjem ini? Ini mungkin mempengaruhi rating Anda.';

  @override
  String get noContinueButton => 'Tidak, Lanjutkan';

  @override
  String get yesCancelRide => 'Ya, Batalkan';

  @override
  String adminOverrideBanner(String reason) {
    return 'Override Admin: $reason';
  }

  @override
  String get driverStatusDrivingToPickup => 'Menuju titik jemput';

  @override
  String get driverStatusArrivedAtPickup => 'Tiba di titik jemput';

  @override
  String get driverStatusRideInProgress => 'Anjem berlangsung';

  @override
  String get driverStatusRideCompleted => 'Anjem selesai';

  @override
  String get driverStatusRideCancelled => 'Anjem dibatalkan';

  @override
  String get logoutTooltip => 'Keluar';

  @override
  String get cancelRideTooltip => 'Batalkan anjem';

  @override
  String get statusOnlineLabel => 'ONLINE';

  @override
  String get statusOfflineLabel => 'OFFLINE';

  @override
  String get riderFallback => 'Penumpang';

  @override
  String radiusCurrentLabel(String radius) {
    return '$radius km radius';
  }

  @override
  String get radiusMinLabel => '0,5 km';

  @override
  String get radiusMaxLabel => '5 km';

  @override
  String get noMaxRadiusLabel => 'Tanpa batas radius jemput';

  @override
  String get noMaxRadiusDesc =>
      'Terima semua permintaan anjem tanpa batasan jarak';

  @override
  String get recenterMap => 'Pusatkan peta';

  @override
  String get dateLabel => 'Tanggal';

  @override
  String get driverLabel => 'Pengemudi';

  @override
  String get maxPassengersHint => 'Maks: 4 penumpang';

  @override
  String get specialRequestsPlaceholder =>
      'mis., \"Harap tunggu di gerbang 2\"';

  @override
  String failedGeneric(String error) {
    return 'Gagal: $error';
  }

  @override
  String get validatorStudentEmailRequired => 'Email mahasiswa wajib diisi';

  @override
  String get validatorInvalidEmailFormat => 'Format email tidak valid';

  @override
  String get validatorEmailDomainInvalid =>
      'Email harus dari institusi akademik (*.ac.id)';

  @override
  String get validatorEmailAlreadyRegistered => 'Email ini sudah terdaftar';

  @override
  String get validatorStudentIdRequired => 'NIM wajib diisi';

  @override
  String get validatorFullNameRequired => 'Nama lengkap wajib diisi';

  @override
  String get validatorRequired => 'Wajib diisi';

  @override
  String get validatorVehicleColorRequired => 'Warna kendaraan wajib dipilih';

  @override
  String get studentEmailHint => 'email.anda@universitas.ac.id';

  @override
  String get studentEmailHelper => 'Harus menggunakan email kampus Anda';

  @override
  String get studentIdHint => 'mis., 24010123140147';

  @override
  String get fullNameHint => 'Nama lengkap Anda';

  @override
  String get selectColorHint => 'Pilih warna kendaraan';

  @override
  String get licensePlateLabel => 'Plat Nomor *';

  @override
  String get licensePlateFormat => 'Format: XX - 1234 - XXX';

  @override
  String get motorcycleOnlyNote => 'Anjem motor — maksimal 1 penumpang';

  @override
  String cashPaymentNote(String amount) {
    return 'Siapkan uang tunai $amount, pembayaran langsung ke driver.';
  }

  @override
  String get altPaymentNote =>
      'Metode pembayaran lain (QRIS, e-wallet) tergantung kesediaan driver.';

  @override
  String get profileSectionTitle => 'Profil';

  @override
  String get personalInfoSectionTitle => 'Informasi Pribadi';

  @override
  String get nameLabel => 'Nama';

  @override
  String get phoneNumberLabel => 'Nomor WhatsApp';

  @override
  String get phoneNumberHint => 'mis., 08123456789';

  @override
  String get emailReadOnlyHint => 'Dari akun Google';

  @override
  String get saveProfileButton => 'Simpan Profil';

  @override
  String get profileSavedSuccess => 'Profil berhasil disimpan';

  @override
  String get failedToSaveProfile =>
      'Gagal menyimpan profil. Silakan coba lagi.';

  @override
  String get changeProfilePicture => 'Ubah Foto Profil';

  @override
  String get takePhotoOption => 'Ambil Foto';

  @override
  String get chooseFromGalleryOption => 'Pilih dari Galeri';

  @override
  String get uploadingAvatar => 'Mengunggah foto...';

  @override
  String get avatarUpdatedSuccess => 'Foto profil diperbarui';

  @override
  String get failedToUpdateAvatar => 'Gagal memperbarui foto profil';

  @override
  String get vehicleInfoSectionTitle => 'Informasi Kendaraan';

  @override
  String get plateNumberLabel => 'Plat Nomor';

  @override
  String get colorLabel => 'Warna';

  @override
  String get accountSectionTitle => 'Akun';

  @override
  String get deleteAccountTitle => 'Hapus Akun';

  @override
  String get deleteAccountConfirmTitle => 'Hapus Akun Anda?';

  @override
  String get deleteAccountConfirmMessage =>
      'Tindakan ini permanen. Semua data Anda akan dihapus. Ketik HAPUS untuk konfirmasi.';

  @override
  String get deleteAccountBothRoleWarning =>
      'Anda terdaftar sebagai penumpang dan pengemudi. Menghapus akun akan menghapus semua data untuk kedua peran.';

  @override
  String get deleteAccountButton => 'Hapus Akun';

  @override
  String get deleteAccountSuccess => 'Akun berhasil dihapus';

  @override
  String get failedToDeleteAccount =>
      'Gagal menghapus akun. Silakan coba lagi.';

  @override
  String get typeDeleteToConfirm => 'Ketik HAPUS untuk konfirmasi';

  @override
  String get aboutSectionTitle => 'Tentang';

  @override
  String get appVersionLabel => 'Versi Aplikasi';

  @override
  String get rideSettingsSectionTitle => 'Pengaturan Anjem';

  @override
  String get phoneRequiredTitle => 'Nomor WhatsApp diperlukan';

  @override
  String get phoneRequiredMessage =>
      'Silakan tambahkan nomor WhatsApp Anda di Pengaturan sebelum memesan. Pengemudi akan menghubungi Anda via WhatsApp.';

  @override
  String get goToSettings => 'Buka Pengaturan';

  @override
  String get openWhatsAppTitle => 'Buka WhatsApp';

  @override
  String openWhatsAppMessage(String name) {
    return 'Chat dengan $name di WhatsApp?';
  }

  @override
  String get whatsAppUnavailable =>
      'Nomor WhatsApp tidak tersedia untuk pengguna ini';

  @override
  String get noActiveDriversMessage => 'Tidak ada pengemudi aktif saat ini.';

  @override
  String get waitOrCancelMessage =>
      'Anda bisa menunggu atau membatalkan — kami tidak memaksa.';

  @override
  String get noDriversAcceptedRound =>
      'Belum ada pengemudi terdekat yang menerima.';

  @override
  String retryingCountdown(int seconds) {
    return 'Masih mencari… pengemudi baru bisa bergabung. $seconds detik tersisa.';
  }

  @override
  String get driverUnavailableTryingNext =>
      'Pengemudi tidak tersedia — mencoba yang berikutnya';

  @override
  String notifyingDriverMinutesAway(int minutes) {
    return 'Menghubungi pengemudi $minutes menit dari sini…';
  }

  @override
  String get legendBeingNotified => 'Sedang dihubungi';

  @override
  String get legendActiveDriver => 'Pengemudi aktif';

  @override
  String get legendUnavailable => 'Tidak tersedia';

  @override
  String get whereToTitle => 'Mau ke mana?';

  @override
  String get pickupFieldHint => 'Lokasi jemput';

  @override
  String get dropoffFieldHint => 'Mau ke mana?';

  @override
  String get recentDestinations => 'Tujuan Terakhir';

  @override
  String get noRecentDestinations => 'Belum ada tujuan terakhir';

  @override
  String get confirmPickup => 'Konfirmasi Jemput';

  @override
  String get confirmDropoff => 'Konfirmasi Tujuan';

  @override
  String get adjustPinPickup => 'Geser peta untuk menyesuaikan titik jemput';

  @override
  String get adjustPinDropoff => 'Geser peta untuk menyesuaikan titik tujuan';

  @override
  String get resolvingLocation => 'Mencari alamat...';

  @override
  String get droppedPin => 'Pin Lokasi';

  @override
  String get yourLocation => 'Lokasi Anda';

  @override
  String get pickThisLocation => 'Pilih lokasi ini?';

  @override
  String cooldownCountdown(int seconds) {
    return 'Coba lagi dalam $seconds detik';
  }

  @override
  String get dragToAdjustHint => 'Geser untuk mengatur';

  @override
  String get requestCancelledSuccess => 'Permintaan dibatalkan';

  @override
  String get thankYouFeedback => 'Terima kasih atas masukan Anda!';

  @override
  String get invalidPhoneFormat =>
      'Masukkan nomor telepon yang valid (mis., 08123456789)';

  @override
  String pickupDistanceKm(String distance) {
    return '$distance km dari sini';
  }

  @override
  String etaToPickup(int minutes) {
    return '~$minutes menit ke titik jemput';
  }

  @override
  String get riderNoPhone => 'Penumpang belum memiliki nomor WhatsApp';

  @override
  String get errorUnexpectedRideState =>
      'Status anjem tidak sesuai. Silakan refresh.';

  @override
  String get errorRideNotFound => 'Anjem tidak ditemukan atau sudah selesai.';

  @override
  String get errorPermissionDenied =>
      'Anda tidak memiliki izin untuk tindakan ini.';

  @override
  String get requestTimeoutExpired =>
      'Waktu permintaan habis — diteruskan ke pengemudi berikutnya';

  @override
  String get colorBlack => 'Hitam';

  @override
  String get colorWhite => 'Putih';

  @override
  String get colorSilver => 'Silver';

  @override
  String get colorGray => 'Abu-abu';

  @override
  String get colorRed => 'Merah';

  @override
  String get colorBlue => 'Biru';

  @override
  String get colorGreen => 'Hijau';

  @override
  String get colorYellow => 'Kuning';

  @override
  String get colorBrown => 'Cokelat';

  @override
  String get colorOrange => 'Oranye';

  @override
  String get colorPurple => 'Ungu';

  @override
  String get colorGold => 'Emas';

  @override
  String get colorOther => 'Lainnya';

  @override
  String get notRatedYet => 'Belum dirate';

  @override
  String get rateButton => 'Rate';

  @override
  String get unratedRidesBubble => 'Drivermu belum dirate nih 👋';

  @override
  String currencyFormat(String amount) {
    return 'Rp $amount';
  }

  @override
  String get earningsHistoryTitle => 'Riwayat Penghasilan';

  @override
  String get earningsToday => 'Hari Ini';

  @override
  String get earningsThisWeek => 'Minggu Ini';

  @override
  String get earningsThisMonth => 'Bulan Ini';

  @override
  String get noEarningsYet => 'Belum ada penghasilan';

  @override
  String fareAmount(String amount) {
    return 'Rp $amount';
  }

  @override
  String get rideHistoryButton => 'Riwayat Anjem';

  @override
  String get ratingHistoryTitle => 'Riwayat Rating';

  @override
  String get overallRating => 'Rating Keseluruhan';

  @override
  String ratingsReceived(int count) {
    return '$count rating diterima';
  }

  @override
  String get noRatingsYet => 'Belum ada rating';

  @override
  String get kycStatusTitle => 'Status Verifikasi';

  @override
  String get kycVerified => 'Terverifikasi';

  @override
  String get kycPendingApproval => 'Menunggu Persetujuan';

  @override
  String get kycEmailPending => 'Email Belum Diverifikasi';

  @override
  String get kycNotSubmitted => 'Belum Dikirim';

  @override
  String get kycSuspended => 'Ditangguhkan';

  @override
  String get kycDetailsTitle => 'Informasi yang Diajukan';

  @override
  String get kycStudentEmail => 'Email Mahasiswa';

  @override
  String get kycStudentId => 'NIM';

  @override
  String get kycStudentName => 'Nama Mahasiswa';

  @override
  String get kycVehicleType => 'Jenis Kendaraan';

  @override
  String get kycVehiclePlate => 'Plat Kendaraan';

  @override
  String get kycVehicleColor => 'Warna Kendaraan';

  @override
  String get kycKtmPhoto => 'Foto KTM';

  @override
  String get editKycButton => 'Edit Data KYC';

  @override
  String get editKycConfirmTitle => 'Edit Data KYC?';

  @override
  String get editKycConfirmMessage =>
      'Mengedit akan mengembalikan Anda ke alur KYC dan perlu diverifikasi ulang. Semua data verifikasi saat ini (NIM, info kendaraan, dokumen) akan dihapus. Riwayat anjem dan rating Anda akan tetap tersimpan.\n\nKetik WARJO untuk konfirmasi.';

  @override
  String get editKycBothRoleWarning =>
      'Anda juga terdaftar sebagai penumpang. Mengedit KYC akan menghapus foto profil dan nomor telepon untuk kedua peran.';

  @override
  String get editKycSuccess => 'Data KYC dihapus — silakan ajukan ulang';

  @override
  String get failedToEditKyc => 'Gagal mengedit data KYC';

  @override
  String get typeWarjoToConfirm => 'Ketik WARJO untuk konfirmasi';

  @override
  String get mustBeOfflineToEdit =>
      'Anda harus offline sebelum mengedit data KYC';

  @override
  String get noKycData => 'Belum ada data verifikasi yang diajukan';

  @override
  String get kickedStaleGps =>
      'Anda dibuat offline karena sinyal GPS hilang. Silakan periksa pengaturan lokasi Anda.';

  @override
  String get kickedZeroCredits =>
      'Anda dibuat offline karena saldo kredit habis. Hubungi admin untuk isi ulang.';

  @override
  String get kickedAdminKick => 'Anda dikeluarkan dari antrean oleh admin.';

  @override
  String get kickedGeneric => 'Anda dibuat offline oleh sistem.';

  @override
  String get driverLocationNotificationTitle => 'Anjem Driver';

  @override
  String get driverLocationNotificationText =>
      'Lokasi Anda dibagikan selama Anda online';

  @override
  String get forceUpdateTitle => 'Pembaruan Diperlukan';

  @override
  String get forceUpdateMessage =>
      'Versi aplikasi kamu sudah tidak didukung. Silakan update ke versi terbaru.';

  @override
  String get forceUpdateButton => 'Update Sekarang';
}
