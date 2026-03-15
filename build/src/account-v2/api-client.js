import axios from 'axios';
import axiosRetry from 'axios-retry';
import Alpine from 'alpinejs';

// Set to true to log request timing to console (disable in production)
const API_TIMING_DEBUG = false;

window.getApiFetcher = function (baseUrl, contentType = 'multipart/form-data', timeout = 30000) {
  const api = axios.create({
    baseURL: baseUrl,
    headers: {
      'Content-Type': contentType,
    },
    timeout: timeout,
    withCredentials: true,
  });

  // Request timing interceptor
  if (API_TIMING_DEBUG) {
    api.interceptors.request.use((config) => {
      config._startTime = performance.now();
      config._retryCount = config['axios-retry']?.retryCount || 0;
      const label = `${config.method.toUpperCase()} ${config.url}`;
      if (config._retryCount > 0) {
        console.warn(`[API_TIMING] RETRY #${config._retryCount} ${label}`);
      } else {
        console.debug(`[API_TIMING] >> ${label}`);
      }
      return config;
    });
  }

  api.interceptors.response.use(
    (response) => {
      if (API_TIMING_DEBUG && response.config._startTime) {
        const ms = (performance.now() - response.config._startTime).toFixed(0);
        const label = `${response.config.method.toUpperCase()} ${response.config.url}`;
        console.debug(`[API_TIMING] << ${label} ${response.status} (${ms}ms)`);
      }
      return response;
    },
    (error) => {
      if (API_TIMING_DEBUG && error.config?._startTime) {
        const ms = (performance.now() - error.config._startTime).toFixed(0);
        const label = `${error.config.method.toUpperCase()} ${error.config.url}`;
        const status = error.response?.status || 'NETWORK_ERR';
        console.warn(`[API_TIMING] << ${label} ${status} FAILED (${ms}ms)`);
      }
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
      if (API_TIMING_DEBUG) {
        console.warn(`[API_TIMING] retry #${retryNumber} delay: ${(delay + randomSum).toFixed(0)}ms`);
      }
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
