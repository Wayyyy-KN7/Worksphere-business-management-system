# WorkSphere — Integrated Business Management System

A web-based business management system built with Laravel and MySQL to centralize employee management, departmental operations, attendance tracking, leave management, and internal company workflows.

## About the Project

WorkSphere is designed to help organizations manage their daily operations through a centralized platform. It aims to improve workflow efficiency, organize company information, and provide role-based access for administrators, managers, and employees.

## Key Features

- **Employee Management** — Manage employee information and profiles.
- **Department Management** — Organize employees by department.
- **Role-Based Access Control** — Support different permissions for administrators, managers, and employees.
- **Attendance Management** — Track check-in, check-out, working hours, and lateness.
- **Leave Management** — Submit leave requests and manage approval workflows.
- **Notifications** — Keep users informed about important activities.
- **Audit Logs** — Track important system activities.

*Note: Feature availability depends on the current implementation.*

## Technology Stack

| Technology | Purpose |
|---|---|
| PHP | Backend programming |
| Laravel | Web application framework |
| MySQL | Relational database |
| HTML | Page structure |
| CSS | Styling and layout |
| JavaScript | Frontend interactivity |
| Git & GitHub | Version control and source code hosting |
| Draw.io & dbdiagram | System diagrams and database design |

## System Roles

- **Admin:** Manage system-wide settings and users.
- **Manager:** Manage department employees and activities.
- **Employee:** Access personal information and assigned activities.

## Installation and Setup

### Prerequisites

- PHP version compatible with the project
- Composer
- MySQL
- Node.js and npm, if frontend assets require them

### Steps

1. Clone the repository:

   ```bash
   git clone https://github.com/Wayyyy-KN7/Worksphere-business-management-system.git
   ```

2. Enter the project directory:

   ```bash
   cd Worksphere-business-management-system
   ```

3. Install PHP dependencies:

   ```bash
   composer install
   ```

4. Create your local environment file:

   ```bash
   cp .env.example .env
   ```

   On Windows, you can also copy `.env.example` to `.env` using File Explorer.

5. Generate the application key:

   ```bash
   php artisan key:generate
   ```

6. Configure your MySQL database credentials in `.env`.

7. Run database migrations if the project is configured for them:

   ```bash
   php artisan migrate
   ```

8. Install frontend dependencies and build assets if required:

   ```bash
   npm install
   npm run build
   ```

9. Start the development server:

   ```bash
   php artisan serve
   ```

10. Open the local URL shown in your terminal.

## Project Status

In development.

## Future Improvements

- Enhance reporting and analytics.
- Improve notification workflows.
- Expand automated testing.
- Improve documentation and deployment procedures.

## License

This project is developed as a software portfolio project. Licensing and reuse permissions should be defined by the repository owner.
