# B. COMPLIANCE CHECKLIST

### 1. Core Functionalities (ISO/IEC 25010 & TAM – 20%)
*   **Main Dashboard**: A central screen where you can instantly see all bookings, revenue, and facility status.
*   **Booking System**: Easy tool to add, edit, and delete guest reservations.
*   **Document Archiving**: Digital storage for files so they don't get lost and are easy to search.
*   **Facility Management**: Controls to update prices and availability of rooms or venues.
*   **Visitor Logs**: A digital list of visitors entering and exiting for security monitoring.

### 2. AI / IoT Integration (15%)
*   **Smart Predictions**: The system guesses busy periods based on past data.
*   **Intelligent Search**: A "Google-like" search bar inside the system to find files quickly.
*   **Smart Alerts**: Automatic notifications for critical events or unauthorized access.
*   **Automated Updates**: Facility statuses update automatically without manual input.
*   **Dynamic Pricing**: Ability to adjust prices automatically when demand is high.

### 3. Microservices / API Integration (10%)
*   **Employee Sync (HR)**: Automatic connection to the HR system to fetch employee records.
*   **Finance Integration**: Automatically sends sales data to the accounting system.
*   **Inventory Connection**: Connected to inventory to check if stocks are available.
*   **Guest Database**: Pulls guest info from the main database so you don't have to type it again.
*   **Modular System**: HR and Finance parts are separate but talk to each other so the system doesn't slow down.

### 4. Physical Server Setup and Configuration (15%)
*   **Local Setup (XAMPP)**: The system runs on your own computer/server using XAMPP/Windows.
*   **Fast Database**: Saving and loading data is fast because the database is optimized.
*   **Local Network Speed**: The system is fast because it uses the local network, not the internet.
*   **Data Control**: You have full control over your data because it stays on your own server.
*   **Backup Ready**: Easy to backup files in case the physical hardware breaks.

### 5. Advanced Security Features (Data Privacy Act & ISO 27001 – 15%)
*   **Admin Levels**: Separation between "Super Admin" and "Normal Staff" to control access.
*   **PIN Security**: Requires a secret PIN code before opening sensitive documents.
*   **Auto-Logout**: The system logs out automatically if left open to prevent snooping.
*   **Access Logs**: Every action in the system is recorded to know who did what.
*   **Secure Restore**: Deleted files can be securely retrieved (restored) if needed.

### 6. Analytics (10%)
*   **Sales Charts**: Graphs showing how much profit was made per day or month.
*   **Usage Reports**: Shows which facilities are most frequently used by guests.
*   **Action Logs**: History of system activities for tracking and monitoring.
*   **Staff Tracking**: Reports to view employee attendance and performance.
*   **Status Graphs**: Pie charts showing how many bookings are confirmed, pending, or cancelled.

### 7. Import and Export Functions / Free Report Format (5%)
*   **Download to Excel**: Ability to download the booking list into an Excel file.
*   **Document Download**: You can save uploaded files from the system to your computer.
*   **Print Ready**: A button to easily print receipts or reports in a clean format.
*   **Retrieve Function**: A "Restore" button to bring back data that was accidentally deleted.
*   **Search Filters**: Easy filtering of reports by date or status (e.g., "Pending" only).


### 8. User Interface (UI) Look and Feel (10%)
*   **Modern Design**: Clean and beautiful look, similar to modern apps (verified Glassmorphism style).
*   **Mobile Friendly**: Looks good and works well on phones or tablets.
*   **Animations**: Smooth movements and loading indicators to make it fun to use.
*   **Easy Colors**: Colors are consistent and easy on the eyes.
*   **Simple Menu**: Buttons are easy to find because the sidebar and tabs are organized.

# C. API INTEGRATION PROCESS

### How we fetch and process API Data (Example: HR System)

**1. Fetching Data (The "Call")**
*   **Tool**: We use **cURL** (Client URL), a built-in PHP tool, to talk to other servers.
*   **Process**: The system acts like a browser. It sends a request to the HR Server URL (`https://hr1.atierahotelandrestaurant.com/api/hr4_api.php`).
*   **Code Example**:
    ```php
    $ch = curl_init(); // Start the call
    curl_setopt($ch, CURLOPT_URL, 'https://hr1.atierahotelandrestaurant.com/api/hr4_api.php'); // Set destination
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Save the answer, don't just print it
    $response = curl_exec($ch); // Execute the call
    ```

**2. Processing Data (The "Translation")**
*   **Format**: The data comes back in **JSON** format (a text format that computers understand easily).
*   **Decoding**: We convert this text into a PHP Array so we can use it in our code.
*   **Filtering**: Before showing it, we check if the data is valid or if it needs to be filtered (e.g., removing deleted employees).

**3. Displaying Data (The "View")**
*   **Frontend**: The processed data is sent to the Dashboard using Javascript (AJAX/Fetch).
*   **Result**: The user sees a clean table of employees, but behind the scenes, the data actually came from a completely different server on the internet!

**Summary of Flow:**
`Your System (cURL)` -> `Request` -> `External Server (HR)` -> `Response (JSON)` -> `Your Dashboard (Table)`
