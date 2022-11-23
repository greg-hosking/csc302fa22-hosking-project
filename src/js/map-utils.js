let map;

/**
 *
 */
function initMap() {
  const center = new google.maps.LatLng(42.3601, -71.0589);
  const styles = [
    {
      featureType: 'poi',
      elementType: 'labels',
      stylers: [{ visibility: 'off' }],
    },
  ];
  const options = {
    center: center,
    zoom: 10,
    disableDefaultUI: true,
    styles: styles,
  };
  map = new google.maps.Map(document.getElementById('map'), options);
}

/**
 *
 * @param {number} lat
 * @param {number} lng
 * @returns {string}
 */
async function latLngToAddress(lat, lng) {
  const response = await fetch(
    'https://maps.googleapis.com/maps/api/geocode/json?latlng=' +
      lat +
      ',' +
      lng +
      '&key=AIzaSyAZ6CVKeAcloJ7XFyikQSr-YtINyTZU1hA'
  );
  const json = await response.json();

  let address;
  if (json.status === 'OK') {
    json.results.every((result) => {
      if (result.formatted_address.includes('+')) {
        address = '';
        return true;
      }
      address = result.formatted_address;
      return false;
    });
  }

  return address;
}

/**
 *
 * @param {string} address
 * @returns {number[] | null}
 */
async function addressToLatLng(address) {
  const response = await fetch(
    'https://maps.googleapis.com/maps/api/geocode/json?address=' +
      address +
      '&key=AIzaSyAZ6CVKeAcloJ7XFyikQSr-YtINyTZU1hA'
  );
  const json = await response.json();

  if (json.status === 'OK') {
    return [
      json.results[0].geometry.location.lat,
      json.results[0].geometry.location.lng,
    ];
  }
  return null;
}

/**
 *
 */
function getCurrentAddress() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(async (position) => {
      // Create a blue marker at the position and center the map on it.
      let marker = new google.maps.Marker({
        position: new google.maps.LatLng(
          position.coords.latitude,
          position.coords.longitude
        ),
        icon: 'src/assets/blue_marker.png',
        animation: google.maps.Animation.DROP,
      });
      marker.setMap(map);
      map.setCenter(marker.getPosition());
      map.setZoom(15);

      // Populate the address input with the approximate address of their current location.
      const addressInput = document.getElementById('address-input');
      addressInput.value = await latLngToAddress(
        position.coords.latitude,
        position.coords.longitude
      );
    });
  }
}

// let map;
// let markers = [];
// let addressInput = document.getElementById('address-input');
// let coords = document.getElementById('coords');

// /**
//  *
//  */
// function createMap() {
//   const center = new google.maps.LatLng(42.519539, -70.896713);
//   const mapOptions = {
//     center: center,
//     zoom: 5,
//     disableDefaultUI: true,
//   };
//   map = new google.maps.Map(document.getElementById('map'), mapOptions);

//   // Create a marker at the location the user clicks on the map.
//   map.addListener('click', (event) => {
//     let marker = new google.maps.Marker({
//       position: event.latLng,
//       map: map,
//       animation: google.maps.Animation.DROP,
//     });
//     // Center the map on the marker when it is clicked.
//     google.maps.event.addListener(marker, 'click', goToMarker);
//     markers.push(marker);
//   });
// }

// /**
//  *
//  */
// async function goToAddress() {
//   const response = await fetch(
//     'https://maps.googleapis.com/maps/api/geocode/json?address=' +
//       addressInput.value +
//       '&key=AIzaSyAZ6CVKeAcloJ7XFyikQSr-YtINyTZU1hA'
//   );
//   const json = await response.json();

//   if (json.status !== 'OK') {
//     alert('Please enter a valid address and try again.');
//     return;
//   }

//   result = json.results[0];
//   let marker = new google.maps.Marker({
//     position: new google.maps.LatLng(
//       result.geometry.location.lat,
//       result.geometry.location.lng
//     ),
//     map: map,
//     animation: google.maps.Animation.DROP,
//   });
//   // Center the map on the marker when it is clicked.
//   google.maps.event.addListener(marker, 'click', goToMarker);
//   markers.push(marker);
//   map.setCenter(marker.getPosition());
//   map.setZoom(15);

//   addressInput.value = result.formatted_address;
//   coords.innerHTML =
//     'Latitude: ' +
//     result.geometry.location.lat +
//     '<br>' +
//     'Longitude: ' +
//     result.geometry.location.lng;
// }

// /**
//  *
//  */
// function goToCurrentLocation() {
//   if (navigator.geolocation) {
//     navigator.geolocation.getCurrentPosition(
//       async (position) => {
//         // Create a marker for the position and center the map on it.
//         let marker = new google.maps.Marker({
//           position: new google.maps.LatLng(
//             position.coords.latitude,
//             position.coords.longitude
//           ),
//           animation: google.maps.Animation.DROP,
//         });
//         marker.setMap(map);
//         map.setCenter(marker.getPosition());
//         map.setZoom(15);

//         // Center the map on the marker when it is clicked.
//         google.maps.event.addListener(marker, 'click', goToMarker);
//         markers.push(marker);

//         const response = await fetch(
//           'https://maps.googleapis.com/maps/api/geocode/json?latlng=' +
//             position.coords.latitude +
//             ',' +
//             position.coords.longitude +
//             '&key=AIzaSyAZ6CVKeAcloJ7XFyikQSr-YtINyTZU1hA'
//         );
//         const json = await response.json();

//         if (json.status === 'OK') {
//           json.results.every((result) => {
//             if (result.formatted_address.includes('+')) {
//               addressInput.value = '';
//               return true;
//             }
//             addressInput.value = result.formatted_address;
//             return false;
//           });
//         }

//         coords.innerHTML =
//           'Latitude: ' +
//           position.coords.latitude +
//           '<br>' +
//           'Longitude: ' +
//           position.coords.longitude;
//       },
//       () => {
//         alert('Geolocation blocked by user.');
//       }
//     );
//   } else {
//     alert('Geolocation is not supported by this browser.');
//   }
// }

// /**
//  *
//  */
// async function goToMarker(event) {
//   console.log(event);

//   const lat = event.latLng.lat();
//   const lng = event.latLng.lng();

//   map.setCenter(new google.maps.LatLng(lat, lng));
//   map.setZoom(15);

//   const response = await fetch(
//     'https://maps.googleapis.com/maps/api/geocode/json?latlng=' +
//       lat +
//       ',' +
//       lng +
//       '&key=AIzaSyAZ6CVKeAcloJ7XFyikQSr-YtINyTZU1hA'
//   );
//   const json = await response.json();

//   if (json.status === 'OK') {
//     json.results.every((result) => {
//       if (result.formatted_address.includes('+')) {
//         addressInput.value = '';
//         return true;
//       }
//       addressInput.value = result.formatted_address;
//       return false;
//     });
//   }
//   coords.innerHTML = 'Latitude: ' + lat + '<br>' + 'Longitude: ' + lng;
// }

// async function getLotsFromDB() {
//   const response = await fetch('http://localhost:8080/router.php/lots');
//   const json = await response.json();
//   console.log(json);

//   if (json.success) {
//     json.data.forEach(async (obj) => {
//       let url =
//         'https://maps.googleapis.com/maps/api/geocode/json?address=' +
//         obj.address +
//         '&key=AIzaSyAZ6CVKeAcloJ7XFyikQSr-YtINyTZU1hA';
//       const response2 = await fetch(url);
//       const json2 = await response2.json();

//       if (json2.status !== 'OK') {
//         alert('Please enter a valid address and try again.');
//         return;
//       }

//       result = json2.results[0];

//       // Create a marker for the position and center the map on it.
//       let marker = new google.maps.Marker({
//         position: new google.maps.LatLng(
//           result.geometry.location.lat,
//           result.geometry.location.lng
//         ),
//         animation: google.maps.Animation.DROP,
//       });
//       marker.setMap(map);
//       // map.setCenter(marker.getPosition());
//       // map.setZoom(15);

//       // Center the map on the marker when it is clicked.
//       google.maps.event.addListener(marker, 'click', goToMarker);
//       markers.push(marker);
//     });
//   }
// }

// /**
//  *
//  */
// function reset() {
//   markers.forEach((marker) => {
//     marker.setMap(null);
//   });
//   addressInput.value = '';
//   coords.innerHTML = '';
// }
