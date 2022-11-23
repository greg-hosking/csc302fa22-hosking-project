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
