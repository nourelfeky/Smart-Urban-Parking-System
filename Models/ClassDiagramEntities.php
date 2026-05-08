<?php

declare(strict_types=1);

require_once __DIR__ . '/../Controllers/AuthController.php';

/**
 * Domain façade aligned with the UML class diagram. The runnable MVC app keeps using
 * Controllers + the `User` persistence model; these types mirror diagram names for coursework/docs.
 *
 * Note: PHP already defines a concrete `User` model for the `users` table, so the diagram’s
 * abstract User is named `ClassDiagramUser` here to avoid a naming collision.
 */
abstract class ClassDiagramUser
{
    public function __construct(
        public string $userID,
        public string $name,
        public string $email,
    ) {
    }
}

final class ClassDiagramDriver extends ClassDiagramUser
{
    public function __construct(
        string $userID,
        string $name,
        string $email,
        public string $licenseNumber,
        public int $loyaltyPoints,
        public string $phoneNumber,
    ) {
        parent::__construct($userID, $name, $email);
    }

    /** @return array{success: bool, redirect?: string, error?: string} */
    public function register(array $postData): array
    {
        return AuthController::register($postData);
    }

    /** @return array{success: bool, redirect?: string, error?: string} */
    public function login(array $postData): array
    {
        return AuthController::login($postData);
    }

    public function updateProfile(): void
    {
        // Profile updates are handled in driver-facing controllers/views in this codebase.
    }

    public function viewReservations(): void
    {
        DriverController::bookings();
    }

    public function makePayment(): void
    {
        // Payments run inside booking / checkout flows (DriverController).
    }
}

final class ClassDiagramAdmin extends ClassDiagramUser
{
    public function __construct(string $userID, string $name, string $email)
    {
        parent::__construct($userID, $name, $email);
    }

    public function manageUsers(): void
    {
        // Admin user management: AdminController routes.
    }

    public function manageParkingSpots(): void
    {
        // AdminController::spots and related actions.
    }

    public function viewReports(): void
    {
        // Admin dashboard / heatmap / fines summaries.
    }

    public function updateConfig(): void
    {
        // Zone VAT, platform knobs — AdminController + DB configuration tables.
    }
}

final class ClassDiagramParkingAttendant extends ClassDiagramUser
{
    public function __construct(
        string $userID,
        string $name,
        string $email,
        public string $attendantID,
    ) {
        parent::__construct($userID, $name, $email);
    }

    public function verifyReservation(): void
    {
        // Operational check-in is implemented as driver QR check-in + owner verify views.
    }

    public function updateSpotStatus(): void
    {
        // Owner spot status toggles — OwnerController.
    }
}

final class ClassDiagramLawEnforcementOfficer extends ClassDiagramUser
{
    public function __construct(
        string $userID,
        string $name,
        string $email,
        public string $officerID,
        public string $badgeNumber,
    ) {
        parent::__construct($userID, $name, $email);
    }

    public function issueFine(): void
    {
        OfficerController::violation();
    }

    public function verifyVehicle(): void
    {
        // Plate lookup against vehicle_profiles / active reservations in officer flows.
    }
}

final class ClassDiagramParkingSpot
{
    public function __construct(
        public string $spotID,
        public string $location,
        public string $type,
        public string $status,
        public float $hourlyRate,
    ) {
    }

    public function updateStatus(): void
    {
        // parking_spots.status — OwnerController / admin approval flows.
    }

    public function getDetails(): void
    {
        // Spot detail via search / book views.
    }
}

final class ClassDiagramReservation
{
    public function __construct(
        public string $reservationID,
        public string $userID,
        public string $spotID,
        public string $startTime,
        public string $endTime,
        public string $status,
        public float $totalAmount,
    ) {
    }

    public function createReservation(): void
    {
        DriverController::book();
    }

    public function cancelReservation(): void
    {
        // DriverController::bookingDetail POST cancel.
    }

    public function extendReservation(): void
    {
        // DriverController::bookingDetail extension branch.
    }

    public function calculateTotal(): void
    {
        // DriverController::calculateBookingCost (private) + PricingEngine / TaxEngine.
    }
}

final class ClassDiagramVehicleProfile
{
    public function __construct(
        public string $vehicleID,
        public string $licensePlate,
        public string $make,
        public string $model,
        public string $color,
    ) {
    }

    public function addVehicle(): void
    {
        DriverController::vehicles();
    }

    public function updateVehicle(): void
    {
        DriverController::vehicles();
    }

    public function removeVehicle(): void
    {
        DriverController::vehicles();
    }
}

final class ClassDiagramPayment
{
    public function __construct(
        public string $paymentID,
        public string $reservationID,
        public float $amount,
        public string $paymentDate,
        public string $status,
    ) {
    }

    public function processPayment(): void
    {
        // PaymentProcessingService + PaymentModel escrow transitions.
    }

    public function refundPayment(): void
    {
        // PaymentModel::refundFunds, driver cancel / admin zone lock.
    }

    public function getPaymentDetails(): void
    {
        // Join on reservations in booking detail.
    }
}

final class ClassDiagramPaymentMethod
{
    public function __construct(
        public string $methodID,
        public string $paymentID,
        public string $type,
        public string $details,
    ) {
    }
}

final class ClassDiagramFine
{
    public function __construct(
        public string $fineID,
        public string $userID,
        public string $vehicleID,
        public float $amount,
        public string $reason,
        public string $issueDate,
        public string $status,
    ) {
    }

    public function issueFine(): void
    {
        OfficerController::violation();
    }

    public function payFine(): void
    {
        DriverController::fines();
    }

    public function getFineDetails(): void
    {
        DriverController::fines();
    }
}

final class ClassDiagramAuditLog
{
    public function __construct(
        public string $logID,
        public string $userID,
        public string $action,
        public string $timestamp,
    ) {
    }
}

final class ClassDiagramReview
{
    public function __construct(
        public string $reviewID,
        public string $userID,
        public string $spotID,
        public int $rating,
        public string $comment,
    ) {
    }
}

final class ClassDiagramDispute
{
    public function __construct(
        public string $disputeID,
        public string $paymentID,
        public string $reason,
        public string $status,
    ) {
    }
}

final class ClassDiagramLocation
{
    public function __construct(
        public string $locationID,
        public string $address,
        public string $coordinates,
    ) {
    }
}
