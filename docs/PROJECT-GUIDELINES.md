# Project Guidelines

## IoT-Based Smart E-Waste Collection and Monitoring System

## I. Project Overview

Students will design, develop, fabricate, program, test, and demonstrate a functional **IoT-Based Smart E-Waste Collection and Monitoring System**. The project should integrate concepts from computer engineering, including embedded systems, sensors and actuators, IoT communication, database management, web development, system integration, and engineering design.

The completed system should allow a registered user to identify themselves, deposit an electronic waste item, measure and validate the deposited item, automatically transfer the item into a collection container, record the transaction in a database, and provide monitoring information through a web-based dashboard.

The project should be treated as an **engineering prototype**, not simply a programming project. Therefore, students are expected to demonstrate proper hardware design, software development, fabrication, integration, calibration, testing, troubleshooting, and technical documentation.

## II. General Project Objectives

At the end of the project, each group should be able to:

1. Design and fabricate a functional smart e-waste collection prototype.
2. Integrate sensors, actuators, microcontrollers, and communication modules into one working system.
3. Implement user identification using QR codes or another instructor-approved identification method.
4. Measure deposited e-waste using a load cell and properly calibrated weight-sensing circuit.
5. Implement a mechanism for verifying that an item has actually been placed on the weighing platform.
6. Develop an automated mechanism for transferring the validated e-waste into the collection bin.
7. Monitor the collection bin's storage level.
8. Develop a database-driven web application for transaction and system monitoring.
9. Implement a points-based incentive mechanism based on valid e-waste deposits.
10. Conduct systematic testing and evaluate the accuracy, reliability, and overall performance of the prototype.

## III. Minimum System Requirements

Each group must develop its own implementation. The following represent the minimum functional requirements, not necessarily the exact components students must use.

| System Area | Minimum Requirement |
|---|---|
| Controller | ESP32 or instructor-approved equivalent |
| User Identification | QR code scanning/authentication |
| Weight Measurement | Load cell + appropriate amplifier/interface |
| Item Verification | Camera or instructor-approved verification method |
| Bin Monitoring | Ultrasonic/distance sensor |
| Automated Transfer | Linear actuator, motorized pusher, servo mechanism, or equivalent |
| User Feedback | LCD/display and/or LED/buzzer indicators |
| Connectivity | Wi-Fi/IoT communication |
| Database | MySQL or equivalent |
| Web Development | PHP, HTML, CSS, JavaScript or instructor-approved stack |
| Dashboard | Real-time/recent collection and system information |
| Reward System | Weight-based points accumulation |
| Administration | User and transaction management |
| Safety | Proper electrical and mechanical protection |

Students may propose alternative components provided that they can justify their engineering decisions and obtain instructor approval before implementation.

## IV. Step-by-Step Project Procedure

### STEP 1 – Problem Definition and Project Planning

Before purchasing components or writing code, the group must clearly define the problem being addressed.

Students should identify the limitations of conventional e-waste collection bins and determine how embedded systems and IoT technologies can improve the process.

**Required outputs:**

- Project title
- Background of the problem
- General and specific objectives
- Scope and limitations
- Target users
- Initial system requirements
- Work assignment per member
- Proposed project timeline

**Checkpoint 1: Project Proposal Approval**

No fabrication should begin until the proposed system architecture is approved.

### STEP 2 – Develop the Conceptual System Design

Prepare an **Input-Process-Output (IPO)** representation of the proposed system.

Students should identify what information enters the system, how that information will be processed, and what outputs will be generated.

**For example:**

- **Input:** Student QR, e-waste item, measured weight, camera input, bin-level data
- **Process:** Authentication, measurement, verification, point computation, automated transfer, database recording
- **Output:** Reward points, transaction record, dashboard update, bin-level status, system notification

**Required outputs:**

- IPO diagram
- Explanation of the conceptual framework

### STEP 3 – Prepare the System Block Diagram

Create a block diagram showing how the hardware and software subsystems communicate.

The diagram should clearly show:

**Power Supply → Controller → Sensors/Input Devices → Processing → Actuators/Outputs → Database/Web Application**

Students must be able to explain the direction of signals and data between components.

**Required outputs:**

- System block diagram
- Component descriptions
- Hardware specifications

### STEP 4 – Design the Operational Flowchart

Prepare a complete flowchart before programming the prototype.

A typical transaction should follow this general sequence:

**Start → Scan QR → Authenticate User → Place E-Waste → Measure Weight → Verify Item → Compute Points → Activate Motor/Actuator → Transfer E-Waste → Update Database → Display Result → Monitor Bin → End/Next Transaction**

The flowchart must also include error conditions such as:

- Invalid QR code
- No item detected
- Invalid/zero weight
- Verification failure
- Bin full
- Database/network failure
- Actuator failure where applicable

**Checkpoint 2: Design Review**

### STEP 5 – Select and Test Individual Hardware Components

Before integrating the entire prototype, each component must be tested independently.

For the load cell, students should calibrate the sensor using known standard weights and record measured versus actual values.

For the QR scanner/camera, students should test detection at different practical distances and lighting conditions.

For the internal monitoring camera, students should verify that the camera can capture or detect the item placed on the weighing platform.

For the ultrasonic sensor, students should compare sensor readings against actual measured distances.

For the actuator or motor, students should test extension/retraction or pushing operations repeatedly.

**Required evidence:**

- Test tables
- Photos/videos of testing
- Calibration records
- Initial source code
- Observed errors and corrective actions

Students should not integrate untested components and hope everything works during final assembly.

## V. Software Development

### STEP 6 – Develop the Embedded System

Program the ESP32 or selected microcontroller to acquire sensor data and control the hardware.

The embedded program should handle, as applicable:

- Sensor initialization
- Weight acquisition
- Sensor calibration
- Bin-level measurement
- LEDs/buzzer
- LCD/display
- Actuator control
- Wi-Fi connection
- Server communication
- Error handling

Students must understand their program and should be able to explain significant portions of their code during evaluation.

### STEP 7 – Develop the Database

Design the database before completing the dashboard.

**Suggested tables may include:**

- `users`
- `organizations`
- `transactions`
- `ewaste_records`
- `reward_points`
- `rewards`
- `redemptions`
- `bin_status`
- `notifications`
- `admins`

Students should apply appropriate primary keys, foreign keys, data types, relationships, and validation.

**Required output:** ERD/database schema.

### STEP 8 – Develop the Web Application

The system should have at least two access levels.

#### User/Student Interface

- Login
- Profile
- Current points
- Disposal/transaction history
- Organization information
- Leaderboard
- Available rewards
- Reward redemption
- Notifications

#### Administrator Interface

- Dashboard
- User management
- E-waste transaction records
- Total collected weight
- Bin-level monitoring
- Reward management
- Redemption management
- Organization/college leaderboard
- Reports or analytics

The interface should be responsive enough to work on both desktop and mobile devices.

## VI. Reward Point Mechanism

### STEP 9 – Develop the Points Algorithm

Students must establish a clear conversion between validated e-waste weight and reward points.

**For example only:**

> 100 grams of validated e-waste = X points

The actual conversion rule should be documented and approved.

The system should follow:

**Authentication → Verification → Weight Validation → Point Calculation → Database Update**

Points must not be awarded solely because the load cell detects weight. The verification mechanism should confirm the transaction before points are credited.

The system should also prevent obvious duplicate or invalid transactions.

## VII. Mechanical Design and Fabrication

### STEP 10 – Design the Enclosure

Before fabrication, prepare a dimensioned design showing:

- Overall machine dimensions
- Weighing platform
- E-waste insertion area
- Actuator/motor location
- Camera location
- Sensor placement
- Electronics compartment
- Collection bin
- Access/service panels
- Cable routing
- Ventilation where necessary

The internal monitoring camera should be positioned so that it can observe the weighing platform while remaining protected inside the machine.

**Required output:** Dimensioned 2D/3D design.

### STEP 11 – Fabricate the Prototype

Fabrication should follow the approved design.

Students should consider:

- Structural stability
- Accessibility for maintenance
- Protection of electronic components
- Cable management
- Moving-part clearance
- Safe actuator operation
- Sensor positioning
- Ease of removing the e-waste container
- Overall appearance

Exposed electrical connections and unsafe moving mechanisms will result in deductions.

## VIII. System Integration

### STEP 12 – Integrate Hardware and Software

After individual modules have passed testing, students may proceed with complete integration.

The expected operational sequence is:

1. System waits for user.
2. Student scans QR code.
3. Database verifies student.
4. System activates the weighing process.
5. Student places e-waste on platform.
6. Load cell measures weight.
7. Internal camera verifies item presence.
8. System validates transaction.
9. Reward points are calculated.
10. Actuator pushes the item into the collection bin.
11. Transaction is recorded in MySQL.
12. Dashboard and student points are updated.
13. Ultrasonic sensor updates bin status.
14. System returns to standby.

## IX. Testing and Validation

### STEP 13 – Conduct Technical Testing

Each group should perform repeated trials instead of demonstrating the system only once.

**At minimum, evaluate:**

| Test | Suggested Metric |
|---|---|
| QR Authentication | Success rate / response time |
| Weight Sensor | Accuracy / % error |
| Camera Verification | Successful verification rate |
| Actuator | Successful operation rate / response time |
| Bin Sensor | Accuracy / % error |
| Database | Successful transaction rate |
| Dashboard | Update/response time |
| Wi-Fi Communication | Successful transmission rate |
| Reward Calculation | Computation accuracy |
| Complete System | Successful transaction rate |

Whenever applicable, students should perform **at least 10 trials per major technical test**.

### Measurement Accuracy

**Percentage Error:**

$$
\text{Percentage Error} =
\left|
\frac{\text{Measured Value} - \text{Actual Value}}
{\text{Actual Value}}
\right|
\times 100
$$

Students must retain the raw test results. Averages without raw data will not be considered sufficient technical evidence.

## X. User Evaluation

### STEP 14 – Evaluate the Completed System

After technical validation, the prototype may undergo user evaluation.

The evaluation instrument may use applicable characteristics from **ISO/IEC 25010**, such as:

- Functional Suitability
- Performance Efficiency
- Interaction Capability/Usability
- Reliability
- Security
- Maintainability

The instructor may also require appropriate **Technology Acceptance Model** measures such as:

- Perceived Usefulness
- Perceived Ease of Use

Students must distinguish technical testing from user evaluation. A Likert questionnaire does not establish sensor accuracy; sensor accuracy should be established through measurement and experimental testing.

## XI. Final Documentation

### STEP 15 – Prepare the Final Technical Report

The final report should contain:

1. Title Page
2. Introduction/Background
3. Objectives
4. Scope and Limitations
5. Significance of the Project
6. Review of Related Literature/Systems
7. Conceptual Framework
8. System Architecture
9. Block Diagram
10. Flowcharts
11. Hardware Design
12. Software Design
13. Database Design/ERD
14. Design and Fabrication Procedures
15. Principles of Operation
16. Testing Procedures
17. Testing Results and Data Analysis
18. System Evaluation
19. Cost Analysis
20. Conclusions
21. Recommendations
22. References
23. Appendices, including source code, raw test data, schematics, BOM, and relevant documentation

## XII. Final Demonstration

During the final demonstration, the group should perform a complete live transaction rather than showing isolated components.

The evaluator may select the e-waste item or test condition. Students should demonstrate:

- Authentication
- Weighing
- Verification
- Automatic disposal
- Point computation
- Database recording
- Dashboard updating
- Bin-level monitoring

The instructor may intentionally introduce reasonable error conditions, such as an invalid QR code or invalid deposit, to determine whether the system handles errors correctly.

Every member should be prepared to answer technical questions.

## XIII. PROJECT RUBRIC – 100 POINTS

| Criteria | Excellent | Good | Satisfactory | Needs Improvement | Weight |
|---|---|---|---|---|---:|
| **Engineering Design & Architecture** | Complete, technically sound, well-integrated design with justified engineering decisions | Minor design issues but generally sound | Functional design with several weaknesses | Incomplete or poorly planned design | 15 |
| **Hardware & Fabrication** | Sensors, controller, actuator and enclosure are properly installed, safe and reliable | Minor installation/fabrication issues | Functional but requires improvement | Major hardware/fabrication problems | 15 |
| **Software, Database & Web System** | Fully functional, organized, responsive and properly integrated | Mostly functional with minor errors | Core features work but with limitations | Major functions incomplete | 15 |
| **System Integration & Automation** | Hardware, software, database and IoT functions operate seamlessly | Minor integration problems | Basic integration works | Components largely operate independently or fail | 15 |
| **Accuracy, Testing & Reliability** | Systematically tested with valid data, repeated trials and appropriate analysis | Good testing with minor deficiencies | Limited testing/analysis | Insufficient or unsupported testing | 15 |
| **Innovation & Application** | Demonstrates meaningful engineering innovation and strong relevance to e-waste management | Good application with some innovative elements | Adequate application | Limited innovation/application | 10 |
| **Technical Documentation** | Complete, accurate and professionally organized with diagrams, results and source documentation | Minor missing information | Several incomplete sections | Poor/incomplete documentation | 10 |
| **Final Demonstration & Technical Defense** | Complete live operation; members clearly explain design and troubleshooting | Mostly successful demonstration and explanation | Partial operation or limited explanation | Major failure or insufficient technical understanding | 5 |
| **TOTAL** | | | | | **100** |

### Suggested Performance Bands

- **90–100: Excellent** – Highly functional, well-engineered and thoroughly validated.
- **80–89: Good** – Functional and technically competent with minor improvements required.
- **70–79: Satisfactory** – Meets minimum project requirements but contains notable limitations.
- **Below 70: Needs Improvement** – Major requirements, functionality, testing, or documentation are incomplete.

## XIV. Individual/Peer Evaluation

To prevent the project from becoming a situation where five names appear on the title page but two students built everything, an individual component can also be included.

| Individual Criterion | Weight |
|---|---:|
| Assigned Task Completion | 30% |
| Technical Contribution | 25% |
| Teamwork and Cooperation | 20% |
| Problem-Solving Initiative | 15% |
| Attendance and Participation | 10% |
| **Total** | **100%** |

The peer evaluation may be used as an individual adjustment factor rather than replacing the project's group grade.
