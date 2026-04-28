CREATE TABLE cb_sessions (
  id VARCHAR(191) NOT NULL,
  user_id BIGINT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  payload TEXT NOT NULL,
  last_activity INT NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE cb_cache (
  `key` VARCHAR(191) NOT NULL,
  value TEXT NOT NULL,
  expiration INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE cb_cache_locks (
  `key` VARCHAR(191) NOT NULL,
  `owner` VARCHAR(191) NOT NULL,
  expiration INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE cb_roles (
  id BIGINT NOT NULL AUTO_INCREMENT,
  name VARCHAR(191) NOT NULL,
  display_name VARCHAR(255) NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY roles_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE cb_permissions (
    id BIGINT NOT NULL AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    display_name VARCHAR(255) NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    module_id INT NULL,
    action VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY permissions_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cb_permission_role (
    permission_id BIGINT NOT NULL,
    role_id BIGINT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    PRIMARY KEY (permission_id, role_id) 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE cb_sub_module_permissions (
    id BIGINT NOT NULL AUTO_INCREMENT,
    role_id BIGINT NOT NULL,
    sub_module_id BIGINT NOT NULL,
    added_by VARCHAR(255) NULL,
    added_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id)    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE cb_sub_modules (
    id BIGINT NOT NULL AUTO_INCREMENT,
    sub_module_name VARCHAR(255) NULL,
    sub_module_url VARCHAR(255) NULL,
    icon VARCHAR(255) NULL,
    `order` INT NULL,
    added_by VARCHAR(255) NULL,
    added_on VARCHAR(255) NULL,
    module_id BIGINT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id)    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cb_modules (
    id BIGINT NOT NULL AUTO_INCREMENT,
    module_name VARCHAR(191) NULL,
    module_url VARCHAR(255) NULL,
    icon VARCHAR(255) NULL,
    module_order INT NULL,
    added_by VARCHAR(255) NULL,
    added_on VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    deleted INT NOT NULL DEFAULT 0,
    deleted_by VARCHAR(255) NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_reason VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY um_sub_modules_module_name_unique (module_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cb_system_configs (
    id INT NOT NULL AUTO_INCREMENT,
    category_id INT NOT NULL,
    display_name VARCHAR(50) NOT NULL,
    precedence INT NULL,
    value TEXT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    setting VARCHAR(50) NOT NULL,
    toggle_boolean INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id)   
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cb_config_categories (
    id INT NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(50) NOT NULL,
    display_name VARCHAR(50) NOT NULL,
    precedence INT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id)   
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE failed_jobs (
    id BIGINT NOT NULL AUTO_INCREMENT,
    uuid VARCHAR(191) NOT NULL,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload TEXT NOT NULL,
    `exception` TEXT NOT NULL,
    failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY um_failed_jobs_uuid_unique (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jobs (
    id BIGINT NOT NULL AUTO_INCREMENT,
    queue VARCHAR(191) NOT NULL,
    payload TEXT NOT NULL,
    attempts SMALLINT NOT NULL,
    reserved_at INT NULL,
    available_at INT NOT NULL,
    created_at INT NOT NULL,
    PRIMARY KEY (id),
    KEY um_jobs_queue_index (queue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cb_audit_logs (
    id BIGINT NOT NULL AUTO_INCREMENT,
    table_name VARCHAR(191) NOT NULL,
    record_id BIGINT NULL,
    operation VARCHAR(191) NOT NULL,
    old_data LONGTEXT NULL,
    new_data LONGTEXT NULL,
    user_id BIGINT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    approved INT NULL,
    approved_by INT NULL,
    approved_on TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE cb_permission_user (
    id BIGINT NOT NULL AUTO_INCREMENT,
    permission_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    user_type VARCHAR(191) NOT NULL,
    UNIQUE KEY cb_permission_user_permission_id_user_id_user_type_unique (permission_id, user_id, user_type),
    PRIMARY KEY (id)   
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- chips.cb_role_user definition

CREATE TABLE `cb_role_user` (
  `role_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `user_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'App\\Models\\User',
  PRIMARY KEY (`role_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



UPDATE sys_hr_payroll_summary s
SET payslip_elements = CONCAT(
    '[{"element_id":1,"amount":', s.nhif, '},',
    '{"element_id":2,"amount":', s.nssf, '},',
    '{"element_id":5,"amount":', s.social_welfare, '},',
    '{"element_id":3,"amount":', s.akiba_sacco_loan, '},',
    '{"element_id":15,"amount":', s.salary_advance, '},',
    '{"element_id":10,"amount":', s.absenteeism, '},',
    '{"element_id":16,"amount":', s.helb_loans, '},',
    '{"element_id":20,"amount":', s.housing_levy, '},',
    '{"element_id":21,"amount":', s.other_deductions, '},',
    '{"element_id":17,"amount":', s.acting_allowance, '},',
    '{"element_id":8,"amount":', s.prorated_deduction, '},',
    '{"element_id":24,"amount":', s.unpaid_leave, '},',
    '{"element_id":25,"amount":', s.guarantor_loan, '},',
    '{"element_id":18,"amount":', s.akiba_share_contribution, '},',
    '{"element_id":19,"amount":', s.salary_arrears, '},',
    '{"element_id":4,"amount":', s.tax_payable, '}]'
)
WHERE period = '2024-05-01';

REMOVE DEFINE IN TRIGGERS


update sys_pm_lease_agreements_27_repeat set cycle_one_invoiced=1 where cycle_id=1

CREATE INDEX idx_lease_id ON sys_pm_clients_returns(lease_id);