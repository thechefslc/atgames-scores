# **Arcade.Net Pinball Scores Tracker**

This is a simple self-hosted web application that allows you to fetch your personal high scores from Arcade.Net, store them in a local SQLite database, and display them in a sortable table. The application is designed to fetch new scores from the Arcade.Net API only if they haven't been updated in the last 24 hours, or if you explicitly choose to force an update.

## **Features**

* **User Login:** Securely log in to your Arcade.Net account.  
* **Personal Score Fetching:** Retrieves your individual high scores from Arcade.Net.  
* **Local Data Storage:** Stores your scores in a lightweight SQLite database (arcadenet\_scores.db) for persistence.  
* **Conditional Fetching:** Automatically checks if your scores have been updated in the last 24 hours. If not, it fetches new data from the API; otherwise, it loads from the local database.  
* **Force Update Option:** A checkbox allows you to manually trigger a fresh data fetch from Arcade.Net, overriding the daily update check.  
* **Sortable Table:** Displays your scores in an interactive table that can be sorted by clicking on column headers (Game Name, Score, Rank, Signature, Recorded At).  
* **Default Sorting:** The table defaults to sorting by "Recorded At" in descending order (newest scores first).  
* **Last Updated Timestamp:** Shows the timestamp of the last successful data fetch from the Arcade.Net API.

## **Technologies Used**

* **Backend:** PHP (with cURL and SQLite PDO extensions)  
* **Database:** SQLite  
* **Frontend:** HTML5, CSS3 (Bootstrap 5 for styling), JavaScript

## **Setup Instructions**

To get this application up and running on your local machine, follow these steps:

### **Prerequisites**

You need a web server with PHP installed and configured. The following PHP extensions are crucial:

* **PHP CLI:** For running the built-in PHP development server.  
* **PHP cURL:** For making HTTP requests to the Arcade.Net API.  
* **PHP SQLite3 / PDO SQLite:** For interacting with the SQLite database.  
* **PHP JSON:** For handling JSON data.

**Example Installation (Ubuntu/Debian):**  
sudo apt update  
sudo apt install php php-cli php-curl php-sqlite3 php-json

**For Windows/macOS:** Consider using XAMPP, WAMP, Laragon (Windows), or Homebrew (macOS) to set up PHP and a web server.

### **File Structure**

Create a project directory (e.g., arcadenet\_app) and place the following files inside it:  
arcadenet\_app/  
├── api.php           \# PHP backend logic  
└── index.html        \# Frontend HTML, CSS, and JavaScript

* **api.php**: Copy the PHP backend code into this file.  
* **index.html**: Copy the HTML frontend code into this file.

### **Database Creation**

The SQLite database file (arcadenet\_scores.db) will be **automatically created** in the same directory as api.php the first time api.php is accessed.  
**Important:** Ensure that the web server user (or your user if using php \-S) has **write permissions** to the arcadenet\_app directory. If you encounter errors related to database access, check your folder permissions.

### **Running the Application**

The simplest way to run this application for local testing is using PHP's built-in development server:

1. **Open your terminal or command prompt.**  
2. **Navigate to your project directory** (e.g., cd arcadenet\_app).  
3. **Start the PHP development server:**  
   php \-S localhost:8000

   This will start a lightweight web server, typically accessible on http://localhost:8000.  
4. Access the application in your web browser:  
   Open your web browser and go to:  
   http://localhost:8000/index.html

## **Usage**

1. **Enter your Arcade.Net Email and Password** in the respective fields.  
2. **Optional: Check "Force update scores from Arcade.Net"** if you want to bypass the 24-hour update check and fetch fresh data immediately.  
3. **Click "Get & Display My Scores".**  
   * The application will attempt to log in, fetch scores (conditionally), save/update them in the local database, and then display them.  
   * A "Last updated from Arcade.Net:" timestamp will appear above the table, indicating when the data was last fetched from the external API.  
4. **Sort the Table:** Click on any column header (Game Name, Score, Rank, Signature, Recorded At) to sort the table rows. The table defaults to sorting by "Recorded At" in descending order (newest first).

## **Important Notes**

* **SSL Verification (CURLOPT\_SSL\_VERIFYPEER, CURLOPT\_SSL\_VERIFYHOST):** The PHP backend explicitly disables SSL certificate verification (CURLOPT\_SSL\_VERIFYPEER \= false and CURLOPT\_SSL\_VERIFYHOST \= false) to match the behavior of the original Python script. **This is generally not recommended for production environments** as it makes your application vulnerable to man-in-the-middle attacks. For production, you should ensure proper SSL certificate handling.  
* **Error Logging:** PHP errors and error\_log messages from api.php will typically appear in your terminal where the php \-S server is running. Check these logs if you encounter issues.
