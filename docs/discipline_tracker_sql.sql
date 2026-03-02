CREATE TABLE discipline_cases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'e.g., DSC-2025-001',
    staff_id BIGINT UNSIGNED NOT NULL COMMENT 'Subject employee',
    position_id BIGINT UNSIGNED NULL,
    
    current_step TINYINT DEFAULT 1 COMMENT '1-14 step progression',
    current_status_id BIGINT UNSIGNED DEFAULT 1 COMMENT 'Open/Closed/Suspended',
    
    severity_level ENUM('Minor', 'Moderate', 'Serious', 'Gross Misconduct') NULL,
    is_anonymous BOOLEAN DEFAULT FALSE,
    
    created_by BIGINT UNSIGNED NOT NULL,
    date_time DATETIME NOT NULL,
    closed_at DATETIME NULL,
    closed_by BIGINT UNSIGNED NULL,
    
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (position_id) REFERENCES positions(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (closed_by) REFERENCES users(id),
    
    INDEX idx_case_number (case_number),
    INDEX idx_staff (staff_id),
    INDEX idx_status (current_status_id)
);

CREATE TABLE discipline_incident_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    incident_date_time DATETIME NOT NULL,
    location VARCHAR(255) NULL,
    incident_type_id BIGINT UNSIGNED NULL COMMENT 'FK to incidents table',
    incident_description TEXT NOT NULL,
    immediate_action_taken TEXT NULL,
    
    reported_by BIGINT UNSIGNED NOT NULL COMMENT 'Person reporting (may differ from created_by)',
    witness_present BOOLEAN DEFAULT FALSE,
    police_involved BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (incident_type_id) REFERENCES incidents(id),
    FOREIGN KEY (reported_by) REFERENCES users(id)
);

CREATE TABLE discipline_incident_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    incident_report_id BIGINT UNSIGNED NOT NULL,
    
    document_name VARCHAR(255) NOT NULL,
    document_path VARCHAR(500) NOT NULL,
    document_type VARCHAR(100) NULL COMMENT 'Photo, Video, Email, etc',
    file_size INT NULL,
    
    uploaded_by BIGINT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (incident_report_id) REFERENCES discipline_incident_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE discipline_case_registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    registered_by BIGINT UNSIGNED NOT NULL COMMENT 'HR person',
    registration_date DATETIME NOT NULL,
    
    hr_notes TEXT NULL,
    priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    estimated_closure_date DATE NULL,
    
    hr_notified BOOLEAN DEFAULT TRUE,
    notification_sent_at DATETIME NULL,
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (registered_by) REFERENCES users(id),
    
    UNIQUE KEY unique_case_registration (case_id)
);

CREATE TABLE discipline_investigators (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    investigator_id BIGINT UNSIGNED NOT NULL COMMENT 'Staff assigned',
    assigned_by BIGINT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL,
    
    investigation_start_date DATE NULL,
    investigation_deadline DATE NULL,
    
    status ENUM('Assigned', 'In Progress', 'Completed', 'Reassigned') DEFAULT 'Assigned',
    
    reassigned_to BIGINT UNSIGNED NULL,
    reassignment_reason TEXT NULL,
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (investigator_id) REFERENCES staff(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    FOREIGN KEY (reassigned_to) REFERENCES staff(id)
);

CREATE TABLE discipline_evidence (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    evidence_type ENUM('Document', 'Photo', 'Video', 'Audio', 'Physical Item', 'Digital', 'Other') NOT NULL,
    evidence_description TEXT NOT NULL,
    evidence_source VARCHAR(255) NULL COMMENT 'Where obtained from',
    
    chain_of_custody TEXT NULL,
    is_original BOOLEAN DEFAULT TRUE,
    
    uploaded_by BIGINT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE discipline_evidence_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evidence_id BIGINT UNSIGNED NOT NULL,
    
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NULL,
    file_size INT NULL,
    
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (evidence_id) REFERENCES discipline_evidence(id) ON DELETE CASCADE
);

CREATE TABLE discipline_witness_statements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    witness_name VARCHAR(255) NOT NULL,
    witness_staff_id BIGINT UNSIGNED NULL COMMENT 'If internal employee',
    witness_contact VARCHAR(255) NULL,
    witness_role VARCHAR(255) NULL COMMENT 'Eyewitness, Character witness, etc',
    
    statement_text TEXT NOT NULL,
    statement_date DATE NOT NULL,
    
    is_sworn BOOLEAN DEFAULT FALSE,
    sworn_before VARCHAR(255) NULL,
    
    recorded_by BIGINT UNSIGNED NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (witness_staff_id) REFERENCES staff(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

CREATE TABLE discipline_witness_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    witness_statement_id BIGINT UNSIGNED NOT NULL,
    
    document_name VARCHAR(255) NOT NULL,
    document_path VARCHAR(500) NOT NULL,
    
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (witness_statement_id) REFERENCES discipline_witness_statements(id) ON DELETE CASCADE
);

CREATE TABLE discipline_investigation_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    note_date DATE NOT NULL,
    note_text TEXT NOT NULL,
    note_type ENUM('Interview', 'Site Visit', 'Analysis', 'Correspondence', 'General') DEFAULT 'General',
    
    is_confidential BOOLEAN DEFAULT FALSE,
    
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE discipline_show_cause_notices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    notice_number VARCHAR(50) NULL,
    issue_date DATE NOT NULL,
    response_deadline DATE NOT NULL,
    
    allegations TEXT NOT NULL,
    potential_consequences TEXT NULL,
    
    issued_by BIGINT UNSIGNED NOT NULL,
    delivered_method ENUM('Hand Delivery', 'Email', 'Registered Mail', 'Courier') NOT NULL,
    delivered_at DATETIME NULL,
    
    acknowledgment_received BOOLEAN DEFAULT FALSE,
    acknowledgment_date DATETIME NULL,
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id),
    
    UNIQUE KEY unique_case_notice (case_id)
);

CREATE TABLE discipline_show_cause_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    show_cause_id BIGINT UNSIGNED NOT NULL,
    
    document_name VARCHAR(255) NOT NULL,
    document_path VARCHAR(500) NOT NULL,
    
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (show_cause_id) REFERENCES discipline_show_cause_notices(id) ON DELETE CASCADE
);

CREATE TABLE discipline_employee_responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    response_date DATE NOT NULL,
    response_text TEXT NOT NULL,
    
    admits_allegations BOOLEAN NULL,
    mitigating_circumstances TEXT NULL,
    
    submitted_by BIGINT UNSIGNED NULL COMMENT 'Could be employee or representative',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    received_by BIGINT UNSIGNED NULL,
    received_at DATETIME NULL,
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users(id),
    FOREIGN KEY (received_by) REFERENCES users(id),
    
    UNIQUE KEY unique_case_response (case_id)
);

CREATE TABLE discipline_response_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    response_id BIGINT UNSIGNED NOT NULL,
    
    document_name VARCHAR(255) NOT NULL,
    document_path VARCHAR(500) NOT NULL,
    
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (response_id) REFERENCES discipline_employee_responses(id) ON DELETE CASCADE
);

CREATE TABLE discipline_hearings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    hearing_date DATETIME NOT NULL,
    hearing_location VARCHAR(255) NULL,
    hearing_type ENUM('Preliminary', 'Full Hearing', 'Appeal') DEFAULT 'Full Hearing',
    
    chairperson_id BIGINT UNSIGNED NULL,
    panel_members JSON NULL COMMENT 'Array of user IDs',
    
    employee_attended BOOLEAN NULL,
    employee_representative VARCHAR(255) NULL,
    
    scheduled_by BIGINT UNSIGNED NOT NULL,
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    rescheduled_from DATETIME NULL,
    reschedule_reason TEXT NULL,
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (chairperson_id) REFERENCES users(id),
    FOREIGN KEY (scheduled_by) REFERENCES users(id)
);

CREATE TABLE discipline_hearing_minutes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hearing_id BIGINT UNSIGNED NOT NULL,
    
    attendees JSON NULL COMMENT 'Names and roles',
    start_time TIME NOT NULL,
    end_time TIME NULL,
    
    proceedings_summary TEXT NOT NULL,
    employee_statement TEXT NULL,
    panel_questions TEXT NULL,
    
    minute_taker_id BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (hearing_id) REFERENCES discipline_hearings(id) ON DELETE CASCADE,
    FOREIGN KEY (minute_taker_id) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    
    UNIQUE KEY unique_hearing_minutes (hearing_id)
);

CREATE TABLE discipline_hearing_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hearing_id BIGINT UNSIGNED NOT NULL,
    
    document_name VARCHAR(255) NOT NULL,
    document_path VARCHAR(500) NOT NULL,
    document_type VARCHAR(100) NULL COMMENT 'Minutes, Recording, Presentation, etc',
    
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (hearing_id) REFERENCES discipline_hearings(id) ON DELETE CASCADE
);

CREATE TABLE discipline_outcomes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    decision_date DATE NOT NULL,
    decision ENUM('No Action', 'Verbal Warning', 'Written Warning', 'Final Warning', 'Suspension', 'Demotion', 'Dismissal', 'Other') NOT NULL,
    
    action_taken_id BIGINT UNSIGNED NULL COMMENT 'FK to action_taken table',
    
    findings TEXT NOT NULL,
    reasoning TEXT NULL,
    sanctions TEXT NULL,
    
    effective_date DATE NULL,
    suspension_days INT NULL COMMENT 'If suspended',
    
    appeal_allowed BOOLEAN DEFAULT TRUE,
    appeal_deadline DATE NULL,
    
    decided_by BIGINT UNSIGNED NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (action_taken_id) REFERENCES action_taken(id),
    FOREIGN KEY (decided_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    
    UNIQUE KEY unique_case_outcome (case_id)
);

CREATE TABLE discipline_outcome_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outcome_id BIGINT UNSIGNED NOT NULL,
    
    document_name VARCHAR(255) NOT NULL,
    document_path VARCHAR(500) NOT NULL,
    
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (outcome_id) REFERENCES discipline_outcomes(id) ON DELETE CASCADE
);

CREATE TABLE discipline_training_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    training_type VARCHAR(255) NOT NULL,
    training_provider VARCHAR(255) NULL,
    training_description TEXT NULL,
    
    scheduled_date DATE NULL,
    completion_deadline DATE NULL,
    
    completed BOOLEAN DEFAULT FALSE,
    completion_date DATE NULL,
    completion_certificate_path VARCHAR(500) NULL,
    
    assigned_by BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);

CREATE TABLE discipline_history_updates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    update_type ENUM('Warning Added', 'Sanction Recorded', 'Performance Review Flag', 'Promotion Block', 'Record Expunged') NOT NULL,
    
    details TEXT NULL,
    expiry_date DATE NULL COMMENT 'When warning expires',
    
    updated_by BIGINT UNSIGNED NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

CREATE TABLE discipline_case_closures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    closure_date DATE NOT NULL,
    closure_reason ENUM('Resolved', 'Withdrawn', 'No Case to Answer', 'Settled', 'Other') NOT NULL,
    closure_notes TEXT NULL,
    
    lessons_learned TEXT NULL,
    policy_recommendations TEXT NULL,
    
    closed_by BIGINT UNSIGNED NOT NULL,
    closed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (closed_by) REFERENCES users(id),
    
    UNIQUE KEY unique_case_closure (case_id)
);

CREATE TABLE discipline_activity_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    step_number TINYINT NOT NULL COMMENT '1-14',
    step_name VARCHAR(100) NOT NULL,
    
    activity_type ENUM('Step Completed', 'Step Skipped', 'Document Added', 'Comment Added', 'Assignment Changed') NOT NULL,
    activity_description TEXT NULL,
    
    performed_by BIGINT UNSIGNED NOT NULL,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    metadata JSON NULL COMMENT 'Additional context data',
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id),
    
    INDEX idx_case_timeline (case_id, performed_at)
);

CREATE TABLE discipline_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    
    notification_type VARCHAR(100) NOT NULL COMMENT 'incident_reported, case_registered, investigator_assigned, etc',
    recipient_user_id BIGINT UNSIGNED NULL,
    recipient_email VARCHAR(255) NULL,
    
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    
    sent_via ENUM('Email', 'SMS', 'System', 'All') DEFAULT 'Email',
    sent_at DATETIME NULL,
    
    read_at DATETIME NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (case_id) REFERENCES discipline_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_user_id) REFERENCES users(id),
    
    INDEX idx_recipient (recipient_user_id, read_at)
);

