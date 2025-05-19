<?php
class User {
    // Get user by id
    public static function getById($id) {
        $sql = "SELECT * FROM users WHERE id = ?";
        $result = executeQuery($sql, "i", [$id]);
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
    
    // Get user by username
    public static function getByUsername($username) {
        $sql = "SELECT * FROM users WHERE username = ?";
        $result = executeQuery($sql, "s", [$username]);
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
    
    // Create new user
    public static function create($data) {
        $sql = "INSERT INTO users (username, password, role, nama_lengkap, alamat, no_hp) VALUES (?, ?, ?, ?, ?, ?)";
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        executeQuery(
            $sql, 
            "ssssss", 
            [
                $data['username'], 
                $hashedPassword, 
                $data['role'], 
                $data['nama_lengkap'], 
                $data['alamat'], 
                $data['no_hp']
            ]
        );
        
        return getConnection()->insert_id;
    }
    
    // Get all users
    public static function getAll() {
        $sql = "SELECT * FROM users ORDER BY created_at DESC";
        $result = executeQuery($sql);
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        return $users;
    }
    
    // Get users by role
    public static function getByRole($role) {
        $sql = "SELECT * FROM users WHERE role = ? ORDER BY created_at DESC";
        $result = executeQuery($sql, "s", [$role]);
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        return $users;
    }
    
    // Update user
    public static function update($id, $data) {
        $sql = "UPDATE users SET nama_lengkap = ?, alamat = ?, no_hp = ? WHERE id = ?";
        executeQuery($sql, "sssi", [$data['nama_lengkap'], $data['alamat'], $data['no_hp'], $id]);
        
        return true;
    }
    
    // Update password
    public static function updatePassword($id, $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        executeQuery($sql, "si", [$hashedPassword, $id]);
        
        return true;
    }
    
    // Delete user
    public static function delete($id) {
        $sql = "DELETE FROM users WHERE id = ?";
        executeQuery($sql, "i", [$id]);
        
        return true;
    }
}
