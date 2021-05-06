import axios from 'axios';
import { APP } from '../config';

/**
 * Generate a URL query string.
 *
 * @param {object} params
 *
 * @return {string}
 */
export async function getForecastTemperature(date) {
  const response = await axios.get(APP.weather_api, {
      "headers": APP.weather_headers
  });

  if (response.status !== 200) {
    return {};
  }

  let days = response.data.data;
  //Filter the day selected 
  let final_day = days.filter((info) => info.datetime == date );
  
  //Return information
  if (final_day.length == 0) {
    return false;
  } else {
    return final_day[0].max_temp;
  }

}

