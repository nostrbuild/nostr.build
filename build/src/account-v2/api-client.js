import axios from 'axios';
import axiosRetry from 'axios-retry';
import Alpine from 'alpinejs';

window.getApiFetcher = function (baseUrl, contentType = 'multipart/form-data', timeout = 30000) {
  const api = axios.create({
    baseURL: baseUrl,
    headers: {
      'Content-Type': contentType,
    },
    timeout: timeout,
    withCredentials: true,
  });

  api.interceptors.response.use(
    (response) => response,
    (error) => {
      if (error.response && error.response.status === 401) {
        console.debug('HTTP 401 Unauthorized error encountered');
        Alpine.store('profileStore').unauthenticated = true;
      }
      return Promise.reject(error);
    }
  );

  axiosRetry(api, {
    retries: 3,
    retryDelay: (
      retryNumber = 0,
      _error = undefined,
      delayFactor = 300
    ) => {
      const delay = 2 ** retryNumber * delayFactor;
      const randomSum = delay * 0.2 * Math.random();
      return delay + randomSum;
    },
    retryCondition: (error) => {
      return axiosRetry.isNetworkOrIdempotentRequestError(error) ||
        axiosRetry.isSafeRequestError(error) ||
        axiosRetry.isRetryableError(error);
    },
  });
  return api;
};

window.multipartApi = async function (url, options) {
  const { method, headers = {}, body, signal } = options;

  const s3ApiFetcher = axios.create({
    timeout: 60000,
    withCredentials: true,
    headers: {
      'accept': 'application/json',
      ...headers
    },
    responseType: 'json',
    maxRedirects: 0,
    keepalive: true,
    adapter: ['fetch', 'xhr', 'http']
  });

  axiosRetry(s3ApiFetcher, {
    retries: 6,
    retryDelay: (retryNumber = 0) => {
      const delayFactor = 300;
      const delay = 2 ** retryNumber * delayFactor;
      const randomSum = delay * 0.2 * Math.random();
      return delay + randomSum;
    },
    retryCondition: (error) => {
      return axiosRetry.isNetworkOrIdempotentRequestError(error) ||
        axiosRetry.isSafeRequestError(error) ||
        axiosRetry.isRetryableError(error);
    }
  });

  try {
    const response = await s3ApiFetcher({
      method,
      url,
      data: body,
      signal
    });

    const responseData = response.data;
    return responseData.data || responseData;
  } catch (error) {
    if (error.response) {
      throw new Error('Unsuccessful request', { cause: error.response });
    }
    throw error;
  }
};
