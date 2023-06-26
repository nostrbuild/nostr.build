<?php

class DbSessionHandler implements SessionHandlerInterface
{
    private $db;

    public function open(string $savePath, string $sessionName): bool
    {
        $this->db = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        return $this->db->connect_errno === 0;
    }

    public function close(): bool
    {
        return $this->db->close();
    }

    public function read(string $id): string
    {
        $stmt = $this->db->prepare("SELECT data FROM sessions WHERE id = ?");
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $record = $result->fetch_assoc();
            return $record['data'];
        }
        return '';
    }

    public function write(string $id, string $data): bool
    {
        $stmt = $this->db->prepare("REPLACE INTO sessions (id, data) VALUES (?, ?)");
        $stmt->bind_param('ss', $id, $data);
        return $stmt->execute();
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->bind_param('s', $id);
        return $stmt->execute();
    }

    public function gc(int $maxlifetime): bool
    {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE timestamp < ?");
        $old = time() - $maxlifetime;
        $stmt->bind_param('i', $old);
        return $stmt->execute();
    }
}

$handler = new DbSessionHandler();
session_set_save_handler($handler, true);

// Start the session, but check if its not started yet befroe that.
// This should be the only place session_start() is called, no need to do it anywhere else.
// For session to work, this file must be included in every file that needs session.
if (session_status() == PHP_SESSION_NONE)
    session_start();
