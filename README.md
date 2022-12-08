# CSC302 FA22 Final Project

## Description

This project is a web application that allows parking lot attendants to manage and provide information about their parking lots to users looking for parking in the area.

## Live Website

Visit the website live on <a href="https://digdug.cs.endicott.edu/~ghosking/csc302fa22-hosking-project/src/">DigDug.</a>

## Features

| Feature                          | Progress      |
| -------------------------------- | ------------- |
| User account management          | ██████████ ✔️ |
| User authentication              | ██████████ ✔️ |
| Parking lot lookup by distance   | █████         |
| Rendering of parking lots on map | █████         |
| Parking lot management           | ███████       |

## API

| Method   | Route                            | Parameters                   | Description                                                                          |
| -------- | -------------------------------- | ---------------------------- | ------------------------------------------------------------------------------------ |
| `POST`   | `/sessions`                      | `email, password`            | Attempts to sign the user in with the given credentials.                             |
| `DELETE` | `/sessions`                      |                              | Signs the user out by destroying the current session.                                |
| `POST`   | `/attendants`                    | `email, password`            | Attempts to create a new attendant with the given credentials.                       |
| `GET`    | `/attendants`                    |                              | Gets all the attendants.                                                             |
| `GET`    | `/attendants/:id`                |                              | Gets the attendant with the given ID.                                                |
| `GET`    | `/attendants/:id/reset_password` |                              | Attempts to email the attendant with the given ID a password reset code.             |
| `PATCH`  | `/attendants/:id/reset_password` | `email, resetCode, password` | Attempts to given attendant's password with the given credentials.                   |
| `POST`   | `/attendants/:id/lots`           | `many`                       | Attempts to create a new lot under the given attendant with the given data.          |
| `GET`    | `/attendants/:id/lots`           |                              | Gets all the lots owned by the attendant with the given ID.                          |
| `GET`    | `/lots`                          |                              | Gets all the lots.                                                                   |
| `GET`    | `/lots/:id`                      |                              | Gets the lot with the given ID.                                                      |
| `PUT`    | `/lots/:id`                      | `many`                       | Attempts to update the given lot's data with the given data.                         |
| `POST`   | `/lots/:id/increment_vacancies`  |                              | Attempts to increment the number of vacancies in the lot with the given ID.          |
| `POST`   | `/lots/:id/decrement_vacancies`  |                              | Attempts to decrement the number of vacancies in the lot with the given ID.          |
| `POST`   | `/lots/:id/attendants`           | `email`                      | Attempts to add an attendant with the given email to the lot with the given ID.      |
| `DELETE` | `/lots/:id/attendants`           | `email`                      | Attempts to remove an attendant with the given email from the lot with the given ID. |

## File Structure

```

src
├─ assets
│ ├─ blue_marker.png
│ ├─ red_marker.png
│ ├─ green_marker.png
│ ├─ yellow_marker.png
│ └─ favicon.ico
├─ js
│ └─ map-utils.js (contains utility functions related to the Google Maps API)
├─ php
│ ├─ router.php (is the main router for the back end that handles routing API requests)
│ ├─ http-utils.php (contains utility functions for sending HTTP response codes)
│ ├─ sessions.php (contains handler functions related to user sign in and sign out)
│ ├─ db-utils.php (contains utility functions for interacting with the database)
│ ├─ attendants.php (contains handler functions related to attendants)
│ ├─ lots.php (contains handler functions related to lots)
│ └─ data.db (is the SQLite database file)
├─ index.html
├─ sign-in.html
├─ parking.html (is the page that allows attendants to manage their parking lots and displays all nearby parking lots to guests)
├─ sign-up.html
├─ forgot-password.html
├─ reset-password.html
└─ your-parking-lots.html (is the page that )
diagrams.pdf (contains entity-relationship models and wireframes)

```

## Data Model

See `diagrams.pdf` for a detailed entity-relationship model. The data in the diagram (attendants, lots, lot attendants, etc) will be stored in an SQLite database on the server. Data that will be maintained on the client side includes session cookies and user preferences (last entered address, etc) (in session storage).
