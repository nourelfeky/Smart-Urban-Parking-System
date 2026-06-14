# CitySlot - Smart Parking Management System

## Overview

**CitySlot** is a comprehensive smart parking management platform designed to optimize urban mobility by connecting drivers with available parking spaces offered by both commercial operators and private space owners.

The platform enables property owners to monetize underutilized parking spaces such as driveways, garages, and private lots, while providing drivers with a seamless experience for searching, reserving, navigating to, and paying for parking spaces.

By combining dynamic pricing, real-time occupancy tracking, reservation management, and automated enforcement mechanisms, CitySlot helps reduce traffic congestion caused by drivers searching for parking while maximizing parking space utilization.

---

## Project Objectives

* Reduce urban traffic congestion.
* Improve parking space utilization.
* Provide additional income opportunities for private parking space owners.
* Automate parking management and enforcement processes.
* Deliver a secure and efficient booking experience for drivers.
* Support municipal authorities with monitoring and urban planning insights.

---

# System Architecture

The system is built using:

### Design Patterns

* MVC (Model-View-Controller) Architecture
* Object-Oriented Programming (OOP)
* Singleton Design Pattern for Database Connectivity

### Architecture Overview

#### Model Layer

Handles:

* Business logic
* Data validation
* Database interactions
* Reservation processing
* Dynamic pricing calculations

#### View Layer

Handles:

* User Interface
* Responsive pages using Bootstrap
* Driver dashboards
* Owner dashboards
* Administrative panels

#### Controller Layer

Handles:

* User requests
* Routing
* Authentication
* Session management
* CRUD operations

---

# User Roles

## 1. Driver Module

Provides functionality for:

* Searching available parking spaces
* Viewing parking details
* Making reservations
* QR-based check-in/check-out
* Digital payment processing
* Booking extensions
* Managing multiple vehicles
* Viewing booking history
* Saving favorite parking locations
* Submitting appeals against fines

---

## 2. Space Owner Module

Allows owners to:

* Register parking spaces
* Manage availability schedules
* Monitor occupancy rates
* View earnings analytics
* Handle reservations
* Set maintenance periods
* Verify ownership documentation
* Generate monthly business reports

---

## 3. Municipal & Enforcement Module

Provides:

* Parking regulation enforcement
* Fine management
* Event-zone controls
* Urban planning analytics
* Owner verification approval
* Dispute management
* Monitoring of occupancy trends

---

# Core Features

## Reservation Management

### Buffer-Time Management

Automatically inserts a 10-minute cooldown period between consecutive bookings to prevent scheduling conflicts.

### QR-Based Check-In / Check-Out

Drivers receive encrypted QR codes used to validate arrival and departure.

### Grace Period Logic

Allows drivers a 5-minute delay before a reservation is marked as a no-show.

### Multi-Vehicle Profile Management

Drivers can maintain multiple vehicle profiles and select the desired vehicle during booking.

### Nearby Alternative Suggestions

Suggests the closest available parking space if the reserved space becomes unavailable.

### Instant Booking Extension

Allows drivers to extend their reservation if the next time slot is available.

### Reservation Cancellation System

Refund percentages are calculated dynamically according to cancellation timing.

Example:

* More than 2 hours before booking → 100% refund
* Less than 2 hours → Partial refund

### Conflict Resolution Engine

Prevents owners from modifying availability schedules that would affect active reservations.

### Blind-Spot Prevention

Parking spaces marked as:

* Maintenance
* Owner Use

are automatically hidden from search results.

### Subscription-Based Commuter Booking

Supports recurring weekly reservations with automatic discount calculations.

---

# Dynamic Pricing & Financial Management

## Peak-Hour Pricing Engine

Adjusts parking rates according to:

* Rush hours
* Special events
* Demand levels
* Occupancy rates

---

## Escrow Payment System

Payments are temporarily locked in escrow until the parking session is completed successfully.

---

## Loyalty Rewards Program

Provides tiered discounts based on:

* Monthly booking frequency
* Driver activity levels
* Membership status

---

## Refund & Dispute Reconciliation

Handles partial refunds when:

* Parking space information is inaccurate
* Accessibility issues occur
* Service expectations are not met

---

## Promotional Code Validation

Supports:

* Time-limited promotions
* Discount validation
* Expiration control
* Abuse prevention

---

## Tax & VAT Simulation

Calculates applicable taxes based on:

* Parking location
* Municipal regulations
* Local tax policies

---

# Enforcement & Compliance

## Automated Fine Generation

Generates digital fines when:

* A vehicle occupies a space without an active reservation
* Parking duration exceeds the allowed reservation period

---

## Evidence-Based Appeals Workflow

Drivers can submit:

* Photos
* Receipts
* Additional evidence

to challenge generated fines.

---

## Municipal Event-Zone Locking

Administrators can temporarily disable listings within a specific geographic area for:

* Security operations
* Public events
* Parades
* Emergency situations

---

## Owner Verification Workflow

Multi-step verification process requiring:

* Government-issued ID
* Utility bill
* Ownership validation

before a parking space can be listed.

---

# Advanced Features

## Last-Mile Navigation Bridge

Provides exact parking coordinates to external navigation applications.

---

## Smart Filter Engine

Allows searching by:

* Vehicle height restrictions
* Vehicle width restrictions
* Electric vehicle charging availability
* Parking type
* Price range

---

## Favorites & History Management

Drivers can:

* Save preferred parking spaces
* Quickly rebook frequently used locations
* Access booking history

---

## Business Analytics Dashboard

Generates monthly reports including:

* Occupancy rates
* Revenue summaries
* Peak usage periods
* Top-performing parking spaces

---

## Waitlist Automation

Users may join waiting lists for full parking areas and receive notifications when spots become available.

---

## Social Review & Trust Score

A weighted scoring system that evaluates owner reliability based on:

* Ratings
* Reviews
* Booking completion rates
* Historical performance

---

# Security Features

- Password hashing and secure login
- Role-based access control (RBAC)
- Session management and validation
- Input validation
- Protection against SQL Injection (prepared statements)
- Secure database queries

---

# Database Management

## Database Technology

* MySQL
* phpMyAdmin

## Database Operations

The system implements full CRUD functionality:

* Create
* Read
* Update
* Delete

for all major entities including:

* Users
* Parking Spaces
* Reservations
* Payments
* Fines
* Reviews
* Vehicles

---

# Technology Stack

## Backend

* PHP


## Frontend

* HTML5
* CSS3
* Bootstrap 5
* JavaScript

## Database

* MySQL
* phpMyAdmin

## Development Paradigm

* Object-Oriented Programming (OOP)
* MVC Architecture

---

# System Workflow

1. Driver searches for parking spaces.
2. Available spaces are displayed with dynamic pricing.
3. Driver selects a space and creates a reservation.
4. Payment is placed into escrow.
5. Driver checks in using QR code.
6. Occupancy status updates in real time.
7. Driver checks out.
8. Payment is released to the owner.
9. Revenue and analytics are updated.
10. Municipal authorities monitor compliance and enforcement activities.

---
## Software Testing

The system was validated using:

* Unit Testing
* Integration Testing
* System Testing
* User Interface Testing
* Security Testing

Testing ensured the correctness, reliability, usability, and security of all major system functionalities.

---

## Software Complexity Metrics

The project was analyzed using the following software quality metrics:

* **Lines of Code (LOC)** – Measures project size and development effort.
* **Cyclomatic Complexity (CCM)** – Measures decision and control-flow complexity.
* **DIT (Depth of Inheritance Tree)** – Measures inheritance depth.
* **NOC (Number of Children)** – Measures class inheritance relationships.
* **CBO (Coupling Between Objects)** – Measures class dependencies.
* **RFC (Response For Class)** – Measures class response complexity.
* **LCOM (Lack of Cohesion of Methods)** – Measures class cohesion and design quality.

The results indicate a maintainable, modular, and scalable object-oriented system architecture.

---
# Authors

This project was developed by the following team members:

- Abdallah Mehany  
- Abdulrahman Hany  
- Fady Selim  
- Hamza Ibrahim  
- Kenzy Hussein  
- Mohamed Alaa  
- Nour Ibrahim  
