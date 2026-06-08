<?php

require_once __DIR__ . '/config.php';

function db_identifier(string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
}

function get_server_dsn(): string {
    return 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
}

function get_database_dsn(): string {
    return 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
}

function get_pdo_options(): array {
    return [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
}

function ensure_database_ready(): void {
    static $ready = false;
    if ($ready) {
        return;
    }

    $server = new PDO(get_server_dsn(), DB_USER, DB_PASS, get_pdo_options());
    $server->exec(
        'CREATE DATABASE IF NOT EXISTS ' . db_identifier(DB_NAME) .
        ' CHARACTER SET ' . DB_CHARSET .
        ' COLLATE ' . DB_COLLATION
    );

    $db = new PDO(get_database_dsn(), DB_USER, DB_PASS, get_pdo_options());
    create_schema($db);

    $ready = true;
}

function create_schema(PDO $db): void {
    $tableOptions = ' ENGINE=InnoDB DEFAULT CHARSET=' . DB_CHARSET . ' COLLATE=' . DB_COLLATION;

    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id VARCHAR(80) PRIMARY KEY,
            email VARCHAR(255) UNIQUE DEFAULT NULL,
            password_hash VARCHAR(255) DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            role ENUM('aluno','professor','tecnico','administrador') NOT NULL DEFAULT 'aluno',
            level VARCHAR(20) DEFAULT NULL,
            course VARCHAR(255) DEFAULT NULL,
            program VARCHAR(50) DEFAULT NULL,
            postgrad_type VARCHAR(50) DEFAULT NULL,
            advisor_id VARCHAR(80) DEFAULT NULL,
            advisor_name VARCHAR(255) DEFAULT NULL,
            research_project TEXT DEFAULT NULL,
            entry_date DATE DEFAULT NULL,
            qualification_deadline DATE DEFAULT NULL,
            advisor_meeting_url VARCHAR(500) DEFAULT NULL,
            article_url VARCHAR(500) DEFAULT NULL,
            qualification_url VARCHAR(500) DEFAULT NULL,
            thesis_url VARCHAR(500) DEFAULT NULL,
            photo_data_url MEDIUMTEXT DEFAULT NULL,
            source VARCHAR(50) DEFAULT 'manual',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )" . $tableOptions);

    $defaultAdminPasswordHash = '$2y$12$BLAMWlJ.gm0ms0aUp.JwuOyXkw1nOGvxPY9.QD0.BdbLAPmsVYLzq';
    $badDefaultAdminPasswordHash = '$2y$12$JBwknBUIerRlrokO9W8HNuSHyOE63s.EvweeL05R/882Emh0i8dcK';

    $db->exec("
        INSERT INTO users (id, email, password_hash, name, role, source)
        VALUES (
            'admin-0000000000000000',
            'admin@labcon.local',
            '" . $defaultAdminPasswordHash . "',
            'Administrador Padrão',
            'administrador',
            'manual'
        ) ON DUPLICATE KEY UPDATE id = id");

    $db->exec("
        UPDATE users
        SET password_hash = '" . $defaultAdminPasswordHash . "'
        WHERE id = 'admin-0000000000000000'
          AND email = 'admin@labcon.local'
          AND role = 'administrador'
          AND source = 'manual'
          AND password_hash = '" . $badDefaultAdminPasswordHash . "'");

    $db->exec("
        CREATE TABLE IF NOT EXISTS labs (
            id VARCHAR(80) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            location VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )" . $tableOptions);

    $db->exec("
        CREATE TABLE IF NOT EXISTS desks (
            id VARCHAR(80) PRIMARY KEY,
            lab_id VARCHAR(80) NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE
        )" . $tableOptions);

    $db->exec("
        CREATE TABLE IF NOT EXISTS reservations (
            id VARCHAR(80) PRIMARY KEY,
            user_id VARCHAR(80) NOT NULL,
            lab_id VARCHAR(80) NOT NULL,
            desk_id VARCHAR(80) NOT NULL,
            day ENUM('Segunda','Terca','Quarta','Quinta','Sexta','Sabado') NOT NULL,
            start_time VARCHAR(5) NOT NULL,
            end_time VARCHAR(5) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE,
            FOREIGN KEY (desk_id) REFERENCES desks(id) ON DELETE CASCADE
        )" . $tableOptions);

    create_index_if_missing($db, 'desks', 'idx_desks_lab_id', 'lab_id');
    create_index_if_missing($db, 'reservations', 'idx_reservations_user_id', 'user_id');
    create_index_if_missing($db, 'reservations', 'idx_reservations_lab_id', 'lab_id');
    create_index_if_missing($db, 'reservations', 'idx_reservations_desk_id', 'desk_id');
    create_index_if_missing($db, 'reservations', 'idx_reservations_day', 'day');
}

function create_index_if_missing(PDO $db, string $table, string $index, string $column): void {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = ? AND table_name = ? AND index_name = ?'
    );
    $stmt->execute([DB_NAME, $table, $index]);

    if ((int) $stmt->fetchColumn() === 0) {
        $db->exec(
            'CREATE INDEX ' . db_identifier($index) .
            ' ON ' . db_identifier($table) .
            ' (' . db_identifier($column) . ')'
        );
    }
}
