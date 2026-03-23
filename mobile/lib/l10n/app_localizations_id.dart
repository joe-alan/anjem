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
  String get riderTagline => 'Antar Jemput anda, kapan saja';

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
      'Anda memiliki perjalanan aktif. Ingin melanjutkan?';

  @override
  String get continuePendingMessage =>
      'Anda memiliki permintaan perjalanan yang tertunda. Ingin melanjutkan?';

  @override
  String get logoutTitle => 'Keluar';

  @override
  String get logoutConfirmMessage => 'Apakah Anda yakin ingin keluar?';

  @override
  String get accountSuspendedBanner =>
      'Akun Anda telah diblokir. Anda tidak dapat memesan perjalanan.';

  @override
  String get activeRideRequestBanner =>
      'Anda memiliki permintaan perjalanan aktif';

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
  String get requestRideButton => 'Pesan Perjalanan';

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
      'Apakah Anda yakin ingin membatalkan permintaan perjalanan ini?';

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
    return 'Sedang dicoba lagi sampai $seconds detik…';
  }

  @override
  String queuePosition(String position) {
    return 'Posisi Antrian: $position';
  }

  @override
  String get pleaseWaitMessage =>
      'Harap tunggu sementara kami mencarikan Anda dengan pengemudi terdekat';

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
  String get rideCancelledTitle => 'Perjalanan Dibatalkan';

  @override
  String get driverCancelledMessage =>
      'Pengemudi Anda membatalkan perjalanan ini.';

  @override
  String adminCancelReason(String reason) {
    return 'Alasan admin: $reason';
  }

  @override
  String get cancelRideTitle => 'Batalkan Perjalanan?';

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
  String get statusRideInProgress => 'Perjalanan berlangsung';

  @override
  String get statusRideCompleted => 'Perjalanan selesai';

  @override
  String get statusRideCancelled => 'Perjalanan dibatalkan';

  @override
  String etaMinutes(String minutes) {
    return 'ETA: $minutes menit';
  }

  @override
  String get rateYourRideTitle => 'Nilai Perjalanan Anda';

  @override
  String get rideCompletedHeading => 'Perjalanan Selesai!';

  @override
  String get fareLabel => 'Tarif:';

  @override
  String get fromLabel => 'Dari:';

  @override
  String get toLabel => 'Ke:';

  @override
  String get howWasYourRide => 'Bagaimana perjalanan Anda?';

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
  String get rideHistoryTitle => 'Riwayat Perjalanan';

  @override
  String get filterAll => 'Semua';

  @override
  String get filterCompleted => 'Selesai';

  @override
  String get filterCancelled => 'Dibatalkan';

  @override
  String get noRidesYet => 'Belum ada perjalanan';

  @override
  String get rideDetailsTitle => 'Detail Perjalanan';

  @override
  String get estimatedFareLabel => 'Estimasi Tarif:';

  @override
  String get passengerCountTitle => 'Jumlah Penumpang';

  @override
  String get specialRequestsHint => 'Permintaan Khusus (Opsional)';

  @override
  String get confirmRequest => 'Konfirmasi Perjalanan';

  @override
  String welcomeDriver(String name) {
    return 'Selamat datang, $name!';
  }

  @override
  String get statusOnlineWithRide => 'Anda memiliki perjalanan aktif';

  @override
  String get statusOnlineIdle => 'Menunggu permintaan perjalanan...';

  @override
  String get statusOfflineMessage =>
      'Aktifkan online untuk mulai menerima permintaan perjalanan';

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
      'Permintaan perjalanan berikutnya akan datang kepada Anda';

  @override
  String driversAheadOfYou(int count) {
    return '$count pengemudi di depan Anda';
  }

  @override
  String get todaysEarningsTitle => 'Penghasilan Hari Ini';

  @override
  String ridesCompletedToday(int count) {
    return '$count perjalanan selesai hari ini';
  }

  @override
  String get ratingLabel => 'Rating';

  @override
  String get totalRidesLabel => 'Total Perjalanan';

  @override
  String get statusLabel => 'Status';

  @override
  String get statusSuspended => 'diblokir';

  @override
  String get statusVerified => 'Terverifikasi';

  @override
  String get statusUnverified => 'Belum Terverifikasi';

  @override
  String get youHaveActiveRide => 'Anda memiliki perjalanan aktif';

  @override
  String get viewActiveRide => 'Lihat Perjalanan Aktif';

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
  String get goOnlineButton => 'Mulai Online';

  @override
  String get activeRideButton => 'Perjalanan Aktif';

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
      'Anda tidak dapat online atau menerima perjalanan sampai saldo diisi ulang. Hubungi admin Anda.';

  @override
  String get lowCreditsMessage =>
      'Saldo Anda hampir habis. Anda masih bisa menerima perjalanan, tapi pertimbangkan menghubungi admin segera.';

  @override
  String get sufficientCreditsMessage =>
      'Anda memiliki cukup kredit untuk online dan menerima perjalanan.';

  @override
  String get howCreditsWork => 'Cara kredit bekerja';

  @override
  String get creditInfo1 =>
      '1 kredit dikurangi setiap kali Anda menerima perjalanan';

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
  String get maxPickupRadiusTitle => 'Radius Jemput Maksimal';

  @override
  String get maxPickupRadiusDesc =>
      'Hanya terima permintaan perjalanan dengan titik jemput dalam jarak ini dari lokasi Anda saat ini.';

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
  String get ktmPhotoPageTitle => 'Upload Foto KTM';

  @override
  String get ktmPhotoPageSubtitle => 'Ambil foto KTM Anda yang jelas';

  @override
  String get studentEmailLabel => 'Email Mahasiswa *';

  @override
  String get studentIdLabel => 'NIM *';

  @override
  String get fullNameLabel => 'Nama Lengkap (sesuai KTM) *';

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
  String get emailCheckError => 'Tidak dapat memeriksa ketersediaan';

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
  String get verificationCodeExpiry =>
      'Kode verifikasi berlaku selama 10 menit. Periksa folder spam jika Anda tidak melihat emailnya.';

  @override
  String get newRideRequestTitle => 'Permintaan Perjalanan Baru';

  @override
  String get acceptWithinLabel => 'Terima dalam';

  @override
  String get requestTimedOut => 'Waktu permintaan habis';

  @override
  String get rideAccepted => 'Perjalanan diterima!';

  @override
  String get rideDeclined => 'Perjalanan ditolak';

  @override
  String get rideCancelledByRider => 'Permintaan perjalanan dibatalkan';

  @override
  String get errorRideCancelledByRider =>
      'Permintaan perjalanan ini dibatalkan oleh penumpang';

  @override
  String get errorRideAlreadyAccepted =>
      'Perjalanan ini sudah diterima oleh pengemudi lain';

  @override
  String get errorInsufficientCredits =>
      'Kredit tidak cukup untuk menerima perjalanan ini';

  @override
  String get errorAlreadyActiveRide => 'Anda sudah memiliki perjalanan aktif';

  @override
  String get errorRideNoLongerAvailable =>
      'Permintaan perjalanan tidak lagi tersedia';

  @override
  String get errorFailedToAccept =>
      'Gagal menerima perjalanan. Silakan coba lagi.';

  @override
  String get destinationLabel => 'Tujuan';

  @override
  String get passengerSingular => 'Penumpang';

  @override
  String get passengerPlural => 'Penumpang';

  @override
  String get specialRequestsTitle => 'Permintaan Khusus';

  @override
  String get acceptRideButton => 'Terima Perjalanan';

  @override
  String get declineButton => 'Tolak';

  @override
  String get acceptingRide => 'Menerima perjalanan...';

  @override
  String get locationPermissionTitle => 'Izin Lokasi Diperlukan';

  @override
  String get locationPermissionMessage =>
      'Aplikasi ini membutuhkan izin lokasi untuk melacak perjalanan dan memperbarui posisi Anda. Harap aktifkan akses lokasi di Pengaturan.';

  @override
  String get rideCompletedSnackbar => 'Perjalanan selesai! 🎉';

  @override
  String get rideCancelledSnackbar => 'Perjalanan dibatalkan';

  @override
  String get rideCancelledByRiderSnackbar =>
      'Perjalanan dibatalkan oleh penumpang';

  @override
  String get activeRideTitle => 'Perjalanan Aktif';

  @override
  String get goBackButton => 'Kembali';

  @override
  String get failedToLoadRide => 'Gagal memuat perjalanan';

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
  String get startRideFab => 'Mulai Perjalanan';

  @override
  String get completeRideFab => 'Selesaikan Perjalanan';

  @override
  String get cancelRideActiveTitle => 'Batalkan Perjalanan';

  @override
  String get cancelRideActiveConfirmMessage =>
      'Apakah Anda yakin ingin membatalkan perjalanan ini? Ini mungkin mempengaruhi rating Anda.';

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
  String get driverStatusRideInProgress => 'Perjalanan berlangsung';

  @override
  String get driverStatusRideCompleted => 'Perjalanan selesai';

  @override
  String get driverStatusRideCancelled => 'Perjalanan dibatalkan';

  @override
  String get logoutTooltip => 'Keluar';

  @override
  String get cancelRideTooltip => 'Batalkan perjalanan';

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
  String get radiusMaxLabel => '20 km';

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
      'Email harus dari domain students.undip.ac.id';

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
  String get studentEmailHint => 'email.anda@students.undip.ac.id';

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
  String get motorcycleOnlyNote => 'Perjalanan motor — maksimal 1 penumpang';
}
