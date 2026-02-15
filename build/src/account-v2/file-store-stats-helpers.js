export function intervalToMilliseconds(interval) {
  const units = {
    m: 60 * 1000,
    h: 60 * 60 * 1000,
    d: 24 * 60 * 60 * 1000,
  };

  const match = interval.match(/^(\d+)([mhd])$/);
  if (!match) {
    throw new Error('Invalid interval format');
  }

  const value = parseInt(match[1], 10);
  const unit = match[2];

  return value * units[unit];
}

export function toStartOfInterval(time, interval) {
  const date = new Date(time);

  const match = interval.match(/^(\d+)([mhd])$/);
  if (!match) {
    throw new Error('Invalid interval format');
  }

  const value = parseInt(match[1], 10);
  const unit = match[2];

  switch (unit) {
    case 'm': {
      const minutes = date.getMinutes();
      date.setMinutes(Math.floor(minutes / value) * value);
      date.setSeconds(0, 0);
      break;
    }
    case 'h': {
      const hours = date.getHours();
      date.setHours(Math.floor(hours / value) * value);
      date.setMinutes(0, 0, 0);
      break;
    }
    case 'd':
      date.setHours(0, 0, 0, 0);
      break;
    default:
      date.setSeconds(0, 0);
      break;
  }

  return date;
}

export function parseData(jsonData, interval) {
  try {
    if (!jsonData || !jsonData.data || !jsonData.meta) throw new Error('Invalid JSON data');

    const metrics = jsonData.meta
      .map(item => item.name)
      .filter(name => name !== 'time');

    const data = jsonData.data.map(item => {
      let time = new Date(item.time * 1000);
      time = toStartOfInterval(time, interval);

      const dataItem = { time };
      metrics.forEach(metric => {
        const value = item[metric];
        if (value !== undefined) {
          dataItem[metric] = parseFloat(value) || 0;
        }
      });

      return dataItem;
    });

    return { data, metrics };
  } catch (error) {
    console.error('Error parsing data:', error);
    return { data: [], metrics: [] };
  }
}

export function generateTimeLabels(startDate, endDate, interval) {
  const labels = [];
  const intervalMs = intervalToMilliseconds(interval);
  const current = new Date(startDate);

  while (current <= endDate) {
    labels.push(new Date(current));
    current.setTime(current.getTime() + intervalMs);
  }

  return labels;
}

export function mergeDataWithLabels(labels, data, metric) {
  const dataMap = new Map();
  data.forEach(item => {
    const timeKey = item.time.getTime();
    dataMap.set(timeKey, item[metric]);
  });

  return labels.map(label => {
    const timeKey = label.getTime();
    return dataMap.get(timeKey) || 0;
  });
}

export function getColor(index, alpha = 1) {
  const colors = [
    'rgba(0, 114, 178, ALPHA)',
    'rgba(230, 159, 0, ALPHA)',
    'rgba(86, 180, 233, ALPHA)',
    'rgba(0, 158, 115, ALPHA)',
    'rgba(240, 228, 66, ALPHA)',
    'rgba(204, 121, 167, ALPHA)',
    'rgba(213, 94, 0, ALPHA)',
  ];
  return colors[index % colors.length].replace('ALPHA', alpha);
}

export function prepareDatasets(labels, data, metrics, abbreviateNumberFn) {
  return metrics.map((metric, index) => {
    const total = mergeDataWithLabels(labels, data, metric).reduce((sum, value) => sum + value, 0);

    return {
      label: metric
        .replace(/_/g, ' ')
        .toLowerCase()
        .replace(/\b\w/g, char => char.toUpperCase()) + ` (${abbreviateNumberFn(total)})`,
      data: mergeDataWithLabels(labels, data, metric),
      borderColor: getColor(index),
      backgroundColor: getColor(index, 0.8),
      fill: true,
      pointRadius: 1,
    };
  });
}
