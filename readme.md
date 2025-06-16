Arcade.Net Pinball Scores Tracker: A Self-Hosted Web Application

This document outlines a self-hosted web application designed to retrieve and manage personal high scores from Arcade.Net. The application facilitates the storage of these scores in a local SQLite database and presents them in a sortable tabular format. A key feature includes intelligent data fetching, wherein new scores are retrieved from the Arcade.Net API only if a prior update has not occurred within a 24-hour period, or if a manual update is explicitly initiated by the user.
Core Functionality

    User Authentication: Provides a secure mechanism for users to log into their Arcade.Net accounts.

    Score Retrieval: Systematically fetches individual high scores directly from the Arcade.Net platform.

    Persistent Local Storage: All retrieved scores are stored locally within a lightweight SQLite database file, arcadenet_scores.db, ensuring data persistence.

    Conditional Data Synchronization: The application intelligently assesses the recency of stored data. If the local scores are less than 24 hours old, data is served from the local database. Otherwise, or upon user command, a fresh data retrieval from the Arcade.Net API is performed.

    Manual Update Override: An explicit option is provided to compel an immediate data synchronization from Arcade.Net, bypassing the standard daily update interval.

    Interactive Data Presentation: Scores are displayed in a dynamic table that supports sorting by any column header, enhancing data analysis and user experience.

    Default Sorting Configuration: The default display of scores is ordered by the "Recorded At" field in descending chronological order, presenting the most recent scores first.

    Last Synchronization Timestamp: The application indicates the precise time of the most recent successful data synchronization with the Arcade.Net API.

    Game-Specific Filtering: Users can click on a pinball table's name within the table to filter the display and show only scores for that specific game. This filtered view is bookmarkable via URL parameters.

    Toggle Hidden Games: Provides the ability to mark individual games as "hidden" or "shown," allowing users to customize their score list. Hidden games are visually distinct and can be toggled in visibility via a dedicated checkbox.

    Dark Mode Support: The user interface includes a dark mode option, with the preference saved locally in the browser for persistence across sessions.

Technical Architecture

    Backend Component: Implemented in PHP, leveraging its capabilities for server-side logic, HTTP request handling (via cURL), and database interaction (via SQLite PDO).

    Database System: Utilizes SQLite, a file-based relational database management system, for efficient local data storage.

    Frontend Interface: Developed using standard web technologies, including HTML5 for structure, CSS3 (augmented by the Bootstrap 5 framework for responsive design and styling), and JavaScript for interactive elements and asynchronous communication with the backend.

Deployment and Operation Guide

To deploy and operate this application on a local environment, please adhere to the following instructions:
System Requirements

A web server environment with PHP installed and properly configured is requisite. The following PHP extensions are specifically required:

    PHP CLI (Command Line Interface): Essential for initiating the built-in PHP development server.

    PHP cURL Extension: Facilitates outbound HTTP requests to external APIs, including Arcade.Net.

    PHP SQLite3 / PDO SQLite Extensions: Provides the necessary interface for managing the SQLite database.

    PHP JSON Extension: Crucial for encoding and decoding JSON data exchanged between the frontend and backend.

Installation Example (for Ubuntu/Debian based Linux distributions):

sudo apt update
sudo apt install php php-cli php-curl php-sqlite3 php-json

For Windows and macOS environments: Users may consider utilizing integrated development environments such as XAMPP, WAMP, or Laragon (Windows), or package managers like Homebrew (macOS) to establish the necessary PHP and web server infrastructure.
Project Directory Structure

Establish a dedicated project directory (e.g., arcadenet_app) and populate it with the following files:

arcadenet_app/
├── api.php           # Contains the PHP backend logic.
├── index.html        # Contains the HTML, CSS, and JavaScript for the user interface.
└── css/              # Directory for CSS files.
    └── style.css     # External stylesheet for the application.

    api.php: Copy the PHP backend code into this file.

    index.html: Copy the HTML frontend code into this file.

    css/style.css: Create a css subfolder and place the provided CSS code into style.css.

Database Initialization

The SQLite database file, named arcadenet_scores.db, will be automatically generated within the same directory as api.php upon its initial execution. No manual database creation steps are required.

Crucial Note on Permissions: The underlying web server process (or the user account executing the PHP development server) must possess write permissions for the arcadenet_app directory. Failure to ensure adequate permissions may result in database access errors.
Execution Procedure

For local development and testing, PHP's built-in development server offers a straightforward execution method:

    Launch a terminal or command prompt.

    Navigate to the project directory (e.g., cd arcadenet_app).

    Initiate the PHP development server:

    php -S localhost:8000

    This command will start a lightweight web server, typically accessible at http://localhost:8000.

    Access the application via a web browser:
    Open your preferred web browser and direct it to:

    http://localhost:8000/index.html

Operational Guidelines

    Credential Entry: Input your Arcade.Net email address and password into the designated fields.

    Optional Data Refresh: Select the "Force update scores from Arcade.Net" checkbox if an immediate and unconditional data synchronization from the API is desired, bypassing the standard daily refresh mechanism.

    Data Retrieval and Display: Click the "Get & Display My Scores" button.

        The application will proceed to authenticate, conditionally fetch scores, store them in the local database, and render them in the user interface.

        A timestamp indicating the last successful data synchronization from Arcade.Net will be displayed above the table.

    Table Sorting: The displayed table supports interactive sorting. Clicking on any column header (e.g., Game Name, Score, Rank, Signature, Recorded At) will reorder the table rows accordingly. By default, the table is sorted by the "Recorded At" column in descending chronological order.

    Game Filtering: Click on a "Game Name" cell within the table to filter the list and show only the scores for that specific game. A "Show All Games" button will appear to clear this filter.

    Toggle Visibility: Use the "Hide" or "Show" buttons in the "Actions" column to change the visibility of individual game scores. The "Show Hidden Games" checkbox allows you to toggle whether hidden rows are displayed or concealed.

    Theme Selection: Utilize the "Dark Mode" switch in the top right corner to alternate between light and dark themes. Your preference will be saved for future visits.

Important Considerations

    SSL Certificate Verification: The PHP backend, mirroring the behavior of the original Python script, explicitly disables SSL certificate verification (CURLOPT_SSL_VERIFYPEER = false and CURLOPT_SSL_VERIFYHOST = false). This practice is strongly discouraged for production deployments due to the significant security vulnerabilities it introduces, including susceptibility to man-in-the-middle attacks.

    Error Reporting: Any errors generated by the PHP backend, including error_log messages, will typically be visible in the terminal window where the php -S server process is active. Consultation of these logs is recommended for troubleshooting purposes.
