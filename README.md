# CSC302 FA22 Final Project

<b>Description</b><br>
This project is a web application that allows parking lot attendants to manage and provide information about their parking lot(s) to users looking for parking in the area.

<b>Live Website</b><br>
View the website live on DigDug here: <a href="https://digdug.cs.endicott.edu/~ghosking/csc302fa22-hosking-project/src/">https://digdug.cs.endicott.edu/~ghosking/csc302fa22-hosking-project/src/</a> 

<b>Files</b><br>
- `diagrams.pdf` contains ER models and wireframes,
- `src/index.html` is the home HTML file that has the front end for the prototype,
- `src/rest-utils.php` has some utility functions for the back end,
- `src/router.php` is the main router for the back end that handles API requests,
- `src/data.db` is the SQLite database file

<b>Features</b><br>
- Account management (25%),
- Authentication (0%),
- Parking lot management (25%),
- Rendering of parking lots on map (50%)

<b>API</b><br>
Here is a list of currently supported API endpoints:
- `GET /lots` -> list of all parking lots

<b>Data Model</b><br>
See diagrams.pdf for a detailed ER model. The data in the diagram will be stored in an SQLite database on the server. Data that will be maintained on the client side includes auth tokens and user preferences (last entered address, etc). 
