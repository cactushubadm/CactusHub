<?php

function table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    if (!table_exists($pdo, $table)) return false;
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $row) {
        if (($row['name'] ?? '') === $column) return true;
    }
    return false;
}

function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!column_exists($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }
}

function migrate_database(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    active INTEGER NOT NULL DEFAULT 1,
    role TEXT NOT NULL DEFAULT 'admin',
    last_login_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    description TEXT,
    event_date TEXT NOT NULL,
    doors_time TEXT,
    venue TEXT,
    address TEXT,
    cover_image TEXT,
    status TEXT NOT NULL DEFAULT 'draft',
    vip_enabled INTEGER NOT NULL DEFAULT 1,
    vip_capacity INTEGER NOT NULL DEFAULT 0,
    sales_start TEXT,
    sales_end TEXT,
    minimum_age INTEGER NOT NULL DEFAULT 18,
    max_tickets_per_order INTEGER NOT NULL DEFAULT 8,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS ticket_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    price REAL NOT NULL DEFAULT 0,
    stock INTEGER NOT NULL DEFAULT 0,
    sold INTEGER NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS vip_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    document TEXT,
    email TEXT,
    phone TEXT,
    companions INTEGER NOT NULL DEFAULT 0,
    notes TEXT,
    status TEXT NOT NULL DEFAULT 'active',
    checked_in_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_number TEXT NOT NULL UNIQUE,
    public_token TEXT UNIQUE,
    event_id INTEGER NOT NULL,
    buyer_name TEXT NOT NULL,
    buyer_email TEXT NOT NULL,
    buyer_phone TEXT,
    buyer_document TEXT,
    total REAL NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'pending',
    payment_mode TEXT NOT NULL DEFAULT 'manual',
    external_reference TEXT,
    external_payment_id TEXT,
    checkout_url TEXT,
    paid_at TEXT,
    expires_at TEXT,
    inventory_released INTEGER NOT NULL DEFAULT 0,
    customer_ip TEXT,
    requires_review INTEGER NOT NULL DEFAULT 0,
    review_reason TEXT,
    email_sent_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE RESTRICT
);
CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    ticket_type_id INTEGER NOT NULL,
    ticket_name TEXT NOT NULL,
    unit_price REAL NOT NULL,
    quantity INTEGER NOT NULL,
    subtotal REAL NOT NULL,
    FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY(ticket_type_id) REFERENCES ticket_types(id) ON DELETE RESTRICT
);
CREATE TABLE IF NOT EXISTS tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    order_item_id INTEGER NOT NULL,
    code TEXT NOT NULL UNIQUE,
    holder_name TEXT,
    status TEXT NOT NULL DEFAULT 'valid',
    checked_in_at TEXT,
    checked_in_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    FOREIGN KEY(checked_in_by) REFERENCES admins(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT,
    is_secret INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id INTEGER,
    action TEXT NOT NULL,
    entity_type TEXT,
    entity_id INTEGER,
    metadata TEXT,
    ip_address TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(admin_id) REFERENCES admins(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS webhook_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider TEXT NOT NULL,
    external_id TEXT,
    event_type TEXT,
    signature_valid INTEGER NOT NULL DEFAULT 0,
    http_status INTEGER,
    payload TEXT,
    error_message TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS checkin_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id INTEGER NOT NULL,
    admin_id INTEGER,
    action TEXT NOT NULL,
    ticket_code TEXT NOT NULL,
    metadata TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY(admin_id) REFERENCES admins(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS email_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    dedupe_key TEXT UNIQUE,
    to_email TEXT NOT NULL,
    to_name TEXT,
    subject TEXT NOT NULL,
    html_body TEXT NOT NULL,
    text_body TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    attempts INTEGER NOT NULL DEFAULT 0,
    max_attempts INTEGER NOT NULL DEFAULT 5,
    next_attempt_at TEXT,
    last_error TEXT,
    sent_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS legal_acceptances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL UNIQUE,
    terms_version TEXT NOT NULL,
    privacy_version TEXT NOT NULL,
    cancellation_version TEXT NOT NULL,
    marketing_opt_in INTEGER NOT NULL DEFAULT 0,
    ip_address TEXT,
    user_agent TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT,
    ip_address TEXT NOT NULL,
    successful INTEGER NOT NULL DEFAULT 0,
    attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_events_date ON events(event_date);
CREATE INDEX IF NOT EXISTS idx_events_status ON events(status);
CREATE INDEX IF NOT EXISTS idx_ticket_types_event ON ticket_types(event_id);
CREATE INDEX IF NOT EXISTS idx_vip_event ON vip_entries(event_id);
CREATE INDEX IF NOT EXISTS idx_vip_document ON vip_entries(document);
CREATE INDEX IF NOT EXISTS idx_orders_event ON orders(event_id);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_expires ON orders(expires_at);
CREATE INDEX IF NOT EXISTS idx_orders_review ON orders(requires_review,status);
CREATE INDEX IF NOT EXISTS idx_tickets_code ON tickets(code);
CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_checkin_created ON checkin_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_email_queue_status ON email_queue(status,next_attempt_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_lookup ON login_attempts(ip_address,email,attempted_at);
SQL);

    // Upgrade databases created by earlier site versions.
    add_column_if_missing($pdo, 'admins', 'role', "TEXT NOT NULL DEFAULT 'admin'");
    add_column_if_missing($pdo, 'admins', 'last_login_at', 'TEXT');
    add_column_if_missing($pdo, 'admins', 'updated_at', 'TEXT');
    add_column_if_missing($pdo, 'events', 'minimum_age', 'INTEGER NOT NULL DEFAULT 18');
    add_column_if_missing($pdo, 'events', 'max_tickets_per_order', 'INTEGER NOT NULL DEFAULT 8');
    add_column_if_missing($pdo, 'vip_entries', 'updated_at', 'TEXT');
    add_column_if_missing($pdo, 'orders', 'public_token', 'TEXT');
    add_column_if_missing($pdo, 'orders', 'expires_at', 'TEXT');
    add_column_if_missing($pdo, 'orders', 'inventory_released', 'INTEGER NOT NULL DEFAULT 0');
    add_column_if_missing($pdo, 'orders', 'customer_ip', 'TEXT');
    add_column_if_missing($pdo, 'orders', 'updated_at', 'TEXT');
    add_column_if_missing($pdo, 'orders', 'requires_review', 'INTEGER NOT NULL DEFAULT 0');
    add_column_if_missing($pdo, 'orders', 'review_reason', 'TEXT');
    add_column_if_missing($pdo, 'orders', 'email_sent_at', 'TEXT');
    add_column_if_missing($pdo, 'tickets', 'checked_in_by', 'INTEGER');

    // Backfill secure customer tokens for legacy orders.
    if (table_exists($pdo, 'orders')) {
        $rows = $pdo->query("SELECT id FROM orders WHERE public_token IS NULL OR public_token='' LIMIT 5000")->fetchAll();
        $st = $pdo->prepare('UPDATE orders SET public_token=? WHERE id=?');
        foreach ($rows as $row) {
            $st->execute([bin2hex(random_bytes(24)), (int)$row['id']]);
        }
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_public_token ON orders(public_token)');
    }

    // One-time secrets/settings used by operational tools.
    $defaults = [
        'cron_token' => bin2hex(random_bytes(24)),
        'terms_version' => '2026-09-07',
        'privacy_version' => '2026-09-07',
        'cancellation_version' => '2026-09-07',
    ];
    $ins = $pdo->prepare('INSERT OR IGNORE INTO settings(setting_key,setting_value,is_secret) VALUES(?,?,?)');
    foreach ($defaults as $key => $value) $ins->execute([$key,$value,$key === 'cron_token' ? 1 : 0]);
}
