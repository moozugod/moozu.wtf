MOOZU - Render/Railway deploy notes

1) Put these files in a GitHub repository.
2) In Render, create a Web Service from the repository and choose Docker.
3) Add environment variables in Render:
   MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE
   SITE_URL=https://your-site.onrender.com
   ADMIN_USERNAME=moozu
   ADMIN_PASSWORD=your-new-admin-password
   JWT_SECRET=your-long-random-secret
4) Import railway_database_setup.sql into the Railway MySQL console.
5) Open /config/database.php on your deployed URL to test DB connection.
