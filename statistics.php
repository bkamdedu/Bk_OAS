-- جداول مربوط به گردش کار
CREATE TABLE workflow_steps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    step_name TEXT NOT NULL,
    description TEXT,
    step_order INTEGER NOT NULL,
    estimated_days INTEGER DEFAULT 1,
    required_approval INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE task_workflows (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    current_step INTEGER,
    status TEXT DEFAULT 'pending',
    started_at DATETIME,
    completed_at DATETIME,
    FOREIGN KEY (task_id) REFERENCES tasks(id),
    FOREIGN KEY (current_step) REFERENCES workflow_steps(id)
);

CREATE TABLE workflow_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    step_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    action_description TEXT,
    user_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id),
    FOREIGN KEY (step_id) REFERENCES workflow_steps(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- جداول مربوط به نامه‌نگاری
CREATE TABLE letter_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    subject TEXT,
    content TEXT,
    letter_type TEXT,
    tags TEXT,
    status TEXT DEFAULT 'active',
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE letter_attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    letter_id INTEGER NOT NULL,
    file_name TEXT NOT NULL,
    file_path TEXT NOT NULL,
    file_size INTEGER,
    uploaded_by INTEGER,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (letter_id) REFERENCES correspondence(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE correspondence_tracking (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    letter_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    status TEXT NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (letter_id) REFERENCES correspondence(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- جداول مربوط به گزارشات
CREATE TABLE activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    details TEXT,
    ip_address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE export_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    export_type TEXT NOT NULL,
    format TEXT NOT NULL,
    record_count INTEGER,
    file_path TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- جداول مربوط به نظرات
CREATE TABLE task_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    attachments TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- جداول مربوط به نوتیفیکیشن‌ها
CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    type TEXT DEFAULT 'info',
    link TEXT,
    is_read INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- درج داده‌های اولیه برای مراحل گردش کار
INSERT INTO workflow_steps (step_name, description, step_order, estimated_days) VALUES 
('ایجاد تسک', 'ایجاد و تعریف اولیه تسک', 1, 1),
('بررسی اولیه', 'بررسی نیازمندی‌ها و اولویت‌بندی', 2, 2),
('اختصاص منابع', 'اختصاص منابع و نیروی انسانی', 3, 1),
('اجرا', 'شروع و اجرای تسک', 4, 5),
('بررسی کیفیت', 'کنترل کیفیت و تست', 5, 2),
('تحویل', 'تحویل نهایی و مستندسازی', 6, 1);

-- درج قالب‌های نمونه برای نامه
INSERT INTO letter_templates (name, subject, content, letter_type) VALUES 
('نامه اداری', 'موضوع نامه اداری', '<p>با سلام و احترام،</p><p>متن نامه در این قسمت قرار می‌گیرد.</p><p>با تشکر</p>', 'اداری'),
('نامه داخلی', 'موضوع نامه داخلی', '<p>همکار گرامی،</p><p>با توجه به موضوع مورد بحث، موارد زیر به استحضار می‌رسد:</p><ul><li>مورد اول</li><li>مورد دوم</li><li>مورد سوم</li></ul><p>با احترام</p>', 'داخلی'),
('نامه رسمی', 'موضوع نامه رسمی', '<p>جناب آقای/سرکار خانم ...</p><p>با سلام،</p><p>با عنایت به ... این نامه تنظیم و ارسال می‌گردد.</p><p>امید است با همکاری شما، موضوع مذکور به بهترین شکل ممکن پیگیری گردد.</p><p>با تقدیم احترام</p>', 'رسمی');