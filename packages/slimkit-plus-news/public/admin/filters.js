import plueMessageBundle from 'plus-message-bundle';

/**
 * ThinkSNS Plus 消息解析器，获取顶部消息.
 *
 * @param {Object} message
 * @param {String} defaultMessage
 * @return {String}
 * @author Seven Du <shiweidu@outlook.com>
 */
export function plusMessageFirst (message, defaultMessage) {
  return plueMessageBundle(message, defaultMessage).getMessage();
}

export function localTime(value) {
  if (!value) {
    return ''
  }
  if (value[value.length - 1] !== 'Z') {
    value = `${value}Z`
  }
  return new Date(value).toLocaleString(
    navigator.language, { hour12: false }
  );
}
