<?php
require_once '../app/models/User.php';

class AuthController {
    // Show login form
    public function loginForm() {
        require '../app/views/auth/login.php';
    }
    
    // Process login
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            // Validate input
            if (empty($username) || empty($password)) {
                setFlashMessage('error', 'Username dan password harus diisi');
                redirect('/');
                return;
            }
            
            // Get user by username
            $user = User::getByUsername($username);
            
            // Check if user exists and password is correct
            if ($user && password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_nama'] = $user['nama_lengkap'];
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        redirect('/admin/dashboard');
                        break;
                    case 'panitia':
                        redirect('/panitia/dashboard');
                        break;
                    case 'warga':
                        redirect('/warga/dashboard');
                        break;
                    case 'berqurban':
                        redirect('/berqurban/dashboard');
                        break;
                    default:
                        redirect('/');
                        break;
                }
                return;
            }
            
            // Login failed
            setFlashMessage('error', 'Username atau password salah');
            redirect('/');
            return;
        }
        
        // If not POST, show login form
        $this->loginForm();
    }
    
    // Show register form
    public function registerForm() {
        require '../app/views/auth/register.php';
    }
    
    // Process register
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $nama_lengkap = $_POST['nama_lengkap'] ?? '';
            $alamat = $_POST['alamat'] ?? '';
            $no_hp = $_POST['no_hp'] ?? '';
            $role = 'warga'; // Default role is warga
            
            // Validate input
            if (empty($username) || empty($password) || empty($confirm_password) || empty($nama_lengkap)) {
                setFlashMessage('error', 'Semua field harus diisi');
                redirect('/register');
                return;
            }
            
            // Check if password and confirm password match
            if ($password !== $confirm_password) {
                setFlashMessage('error', 'Password dan konfirmasi password tidak cocok');
                redirect('/register');
                return;
            }
            
            // Check if username already exists
            $existingUser = User::getByUsername($username);
            if ($existingUser) {
                setFlashMessage('error', 'Username sudah digunakan');
                redirect('/register');
                return;
            }
            
            // Create user
            $userData = [
                'username' => $username,
                'password' => $password,
                'role' => $role,
                'nama_lengkap' => $nama_lengkap,
                'alamat' => $alamat,
                'no_hp' => $no_hp
            ];
            
            $userId = User::create($userData);
            
            if ($userId) {
                setFlashMessage('success', 'Registrasi berhasil, silakan login');
                redirect('/');
                return;
            } else {
                setFlashMessage('error', 'Registrasi gagal');
                redirect('/register');
                return;
            }
        }
        
        // If not POST, show register form
        $this->registerForm();
    }
    
    // Logout
    public function logout() {
        // Destroy session
        session_destroy();
        
        // Redirect to login page
        redirect('/');
    }
}
