PRAGMA foreign_keys = ON;

DROP TABLE IF EXISTS acl_document;
DROP TABLE IF EXISTS acl_namespace;
DROP TABLE IF EXISTS BlockHistory;
DROP TABLE IF EXISTS cookies;
DROP TABLE IF EXISTS editrequest;
DROP TABLE IF EXISTS email_keys;
DROP TABLE IF EXISTS files;
DROP TABLE IF EXISTS history;
DROP TABLE IF EXISTS links;
DROP TABLE IF EXISTS login_history;
DROP TABLE IF EXISTS remember_login;
DROP TABLE IF EXISTS search_index;
DROP TABLE IF EXISTS starred;
DROP TABLE IF EXISTS thread_content;
DROP TABLE IF EXISTS thread;
DROP TABLE IF EXISTS webauthn;
DROP TABLE IF EXISTS aclgroups;
DROP TABLE IF EXISTS config;
DROP TABLE IF EXISTS document;
DROP TABLE IF EXISTS ip;
DROP TABLE IF EXISTS member;

CREATE TABLE aclgroups (
  groupid INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE
);

CREATE TABLE config (
  key TEXT NOT NULL,
  value TEXT NOT NULL
);

CREATE TABLE document (
  uuid BLOB NOT NULL PRIMARY KEY,
  namespace TEXT NOT NULL,
  title TEXT NOT NULL COLLATE BINARY,
  status TEXT NOT NULL DEFAULT 'normal',
  backlink_updated INTEGER NOT NULL DEFAULT 0,
  UNIQUE (namespace, title)
);

CREATE INDEX document_namespace_title_idx ON document(namespace, title);

CREATE TABLE ip (
  uuid BLOB NOT NULL PRIMARY KEY,
  ip BLOB UNIQUE
);

CREATE TABLE member (
  uuid BLOB NOT NULL PRIMARY KEY,
  username TEXT UNIQUE,
  password TEXT,
  email TEXT NOT NULL UNIQUE,
  last_login_ua TEXT,
  skin TEXT,
  registered INTEGER NOT NULL DEFAULT (unixepoch()),
  registered_ip BLOB,
  totp_secret TEXT,
  perm TEXT,
  settings TEXT
);

CREATE TABLE acl_document (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  uuid BLOB NOT NULL,
  access TEXT NOT NULL,
  condition TEXT NOT NULL,
  action TEXT NOT NULL,
  until INTEGER NOT NULL,
  FOREIGN KEY (uuid) REFERENCES document(uuid)
);

CREATE INDEX acl_document_uuid_idx ON acl_document(uuid);

CREATE TABLE acl_namespace (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  namespace TEXT NOT NULL,
  access TEXT NOT NULL,
  condition TEXT NOT NULL,
  action TEXT NOT NULL,
  until INTEGER NOT NULL
);

CREATE TABLE BlockHistory (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  executor_m BLOB,
  executor_i BLOB,
  target_ip BLOB,
  mask INTEGER,
  target_member BLOB,
  target_ip_uuid BLOB,
  target_aclgroup TEXT,
  comment TEXT,
  datetime INTEGER NOT NULL DEFAULT (unixepoch()),
  until INTEGER,
  action TEXT NOT NULL,
  granted TEXT,
  FOREIGN KEY (executor_m) REFERENCES member(uuid),
  FOREIGN KEY (target_member) REFERENCES member(uuid),
  FOREIGN KEY (target_ip_uuid) REFERENCES ip(uuid)
);

CREATE INDEX blockhistory_executor_idx ON BlockHistory(executor_m);
CREATE INDEX blockhistory_target_member_idx ON BlockHistory(target_member);
CREATE INDEX blockhistory_target_ip_uuid_idx ON BlockHistory(target_ip_uuid);
CREATE INDEX blockhistory_target_aclgroup_idx ON BlockHistory(target_aclgroup);

CREATE TABLE cookies (
  user BLOB NOT NULL,
  name TEXT NOT NULL,
  value BLOB NOT NULL UNIQUE,
  expiry INTEGER NOT NULL,
  created INTEGER NOT NULL DEFAULT (unixepoch()),
  FOREIGN KEY (user) REFERENCES member(uuid) ON DELETE CASCADE
);

CREATE INDEX cookies_user_idx ON cookies(user);

CREATE TABLE email_keys (
  email TEXT NOT NULL PRIMARY KEY,
  ip BLOB NOT NULL,
  key BLOB NOT NULL,
  time INTEGER NOT NULL DEFAULT (unixepoch())
);

CREATE INDEX email_keys_key_idx ON email_keys(key);
CREATE INDEX email_keys_time_idx ON email_keys(time);

CREATE TABLE editrequest (
  urlstr TEXT NOT NULL PRIMARY KEY,
  document BLOB NOT NULL,
  status TEXT NOT NULL DEFAULT 'open',
  comment TEXT,
  content TEXT NOT NULL,
  contributor_m BLOB,
  contributor_i BLOB,
  baserev INTEGER NOT NULL,
  count INTEGER NOT NULL DEFAULT 0,
  datetime INTEGER NOT NULL DEFAULT (unixepoch()),
  lastedit INTEGER NOT NULL DEFAULT (unixepoch()),
  executor_m BLOB,
  executor_i BLOB,
  acceptrev INTEGER,
  reason TEXT,
  FOREIGN KEY (document) REFERENCES document(uuid),
  FOREIGN KEY (contributor_m) REFERENCES member(uuid),
  FOREIGN KEY (contributor_i) REFERENCES ip(uuid),
  FOREIGN KEY (executor_m) REFERENCES member(uuid),
  FOREIGN KEY (executor_i) REFERENCES ip(uuid)
);

CREATE INDEX editrequest_document_idx ON editrequest(document);

CREATE TABLE files (
  uuid BLOB NOT NULL,
  hash BLOB NOT NULL UNIQUE,
  width INTEGER NOT NULL,
  height INTEGER NOT NULL,
  FOREIGN KEY (uuid) REFERENCES document(uuid)
);

CREATE INDEX files_uuid_idx ON files(uuid);

CREATE TABLE history (
  uuid BLOB NOT NULL PRIMARY KEY,
  document BLOB NOT NULL,
  rev INTEGER NOT NULL DEFAULT 1,
  content TEXT,
  comment TEXT NOT NULL DEFAULT '',
  datetime INTEGER NOT NULL DEFAULT (unixepoch()),
  action TEXT NOT NULL,
  count INTEGER NOT NULL DEFAULT 0,
  reverted_version INTEGER,
  contributor_m BLOB,
  contributor_i BLOB,
  edit_request_uri TEXT,
  acl_changed TEXT,
  moved_from TEXT,
  moved_to TEXT,
  status TEXT NOT NULL DEFAULT 'normal',
  hide_log_user BLOB,
  mark_troll_user BLOB,
  revstatus TEXT NOT NULL DEFAULT 'normal',
  FOREIGN KEY (document) REFERENCES document(uuid),
  FOREIGN KEY (contributor_i) REFERENCES ip(uuid),
  FOREIGN KEY (contributor_m) REFERENCES member(uuid),
  FOREIGN KEY (hide_log_user) REFERENCES member(uuid),
  FOREIGN KEY (mark_troll_user) REFERENCES member(uuid)
);

CREATE INDEX history_document_rev_idx ON history(document, rev);
CREATE INDEX history_datetime_idx ON history(datetime);
CREATE INDEX history_contributor_i_idx ON history(contributor_i);
CREATE INDEX history_contributor_m_idx ON history(contributor_m);

CREATE TABLE links (
  namespace TEXT NOT NULL,
  title TEXT NOT NULL,
  from_uuid BLOB NOT NULL,
  type TEXT NOT NULL,
  UNIQUE (namespace, title, from_uuid, type),
  FOREIGN KEY (from_uuid) REFERENCES document(uuid)
);

CREATE INDEX links_from_uuid_idx ON links(from_uuid);
CREATE INDEX links_namespace_title_idx ON links(namespace, title);

CREATE TABLE login_history (
  uuid BLOB NOT NULL,
  ip TEXT NOT NULL,
  datetime INTEGER NOT NULL DEFAULT (unixepoch()),
  FOREIGN KEY (uuid) REFERENCES member(uuid)
);

CREATE INDEX login_history_uuid_idx ON login_history(uuid);

CREATE TABLE remember_login (
  user BLOB NOT NULL,
  ip BLOB NOT NULL,
  useragent TEXT NOT NULL,
  FOREIGN KEY (user) REFERENCES member(uuid)
);

CREATE INDEX remember_login_user_idx ON remember_login(user);

CREATE TABLE search_index (
  document BLOB NOT NULL PRIMARY KEY,
  text TEXT NOT NULL,
  FOREIGN KEY (document) REFERENCES document(uuid)
);

CREATE TABLE starred (
  document BLOB NOT NULL,
  user BLOB NOT NULL,
  PRIMARY KEY (document, user),
  FOREIGN KEY (document) REFERENCES document(uuid),
  FOREIGN KEY (user) REFERENCES member(uuid) ON DELETE CASCADE
);

CREATE INDEX starred_user_idx ON starred(user);

CREATE TABLE thread (
  urlstr TEXT NOT NULL PRIMARY KEY,
  document BLOB NOT NULL,
  topic TEXT NOT NULL,
  status TEXT NOT NULL,
  FOREIGN KEY (document) REFERENCES document(uuid)
);

CREATE INDEX thread_document_idx ON thread(document);

CREATE TABLE thread_content (
  urlstr TEXT NOT NULL,
  no INTEGER NOT NULL,
  contributor_m BLOB,
  contributor_i BLOB,
  type TEXT,
  content TEXT NOT NULL,
  datetime INTEGER NOT NULL DEFAULT (unixepoch()),
  blind TEXT,
  PRIMARY KEY (urlstr, no),
  FOREIGN KEY (urlstr) REFERENCES thread(urlstr),
  FOREIGN KEY (contributor_i) REFERENCES ip(uuid),
  FOREIGN KEY (contributor_m) REFERENCES member(uuid)
);

CREATE INDEX thread_content_urlstr_idx ON thread_content(urlstr);
CREATE INDEX thread_content_contributor_i_idx ON thread_content(contributor_i);
CREATE INDEX thread_content_contributor_m_idx ON thread_content(contributor_m);

CREATE TABLE webauthn (
  uuid BLOB NOT NULL,
  name TEXT NOT NULL,
  registered INTEGER NOT NULL DEFAULT (unixepoch()),
  lastuse INTEGER,
  client_data TEXT NOT NULL,
  UNIQUE (uuid, name),
  FOREIGN KEY (uuid) REFERENCES member(uuid) ON DELETE CASCADE
);
