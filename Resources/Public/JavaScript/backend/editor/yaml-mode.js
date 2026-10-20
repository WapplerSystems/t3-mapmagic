import { StreamLanguage } from '@codemirror/language';

/**
 * Minimal YAML highlighting for <typo3-t3editor-codemirror>; the core ships
 * no YAML mode. Covers what map definitions use: keys, comments, quoted and
 * plain scalars, numbers, booleans, list dashes and block scalars (| and >).
 * Parsing and error reporting happen on the server, this is display only.
 */
const yamlParser = {
  name: 'yaml',
  startState: () => ({ blockIndent: -1 }),

  token(stream, state) {
    // Inside a block scalar every more-indented line is plain text.
    if (state.blockIndent >= 0) {
      if (stream.sol() && (stream.indentation() > state.blockIndent || stream.match(/^\s*$/, false))) {
        stream.skipToEnd();
        return 'string';
      }
      state.blockIndent = -1;
    }
    if (stream.eatSpace()) return null;

    if (stream.peek() === '#') {
      stream.skipToEnd();
      return 'comment';
    }
    if (stream.sol() && stream.match(/^(---|\.\.\.)\s*$/)) return 'meta';
    if (stream.match(/^-(?=\s|$)/)) return 'punctuation';

    // A key is everything up to ": " (or ":" at the end of the line).
    if (stream.match(/^(?:"(?:[^"\\]|\\.)*"|'(?:[^']|'')*'|[^\s#'"][^:#]*?)(?=\s*:(?:\s|$))/)) return 'propertyName';
    if (stream.match(/^:(?=\s|$)/)) {
      // "key: |" or "key: >-" starts a block scalar on the following lines.
      if (stream.match(/^\s*[|>][-+0-9]*\s*(?:#.*)?$/, false)) {
        state.blockIndent = stream.indentation();
      }
      return 'punctuation';
    }
    if (stream.match(/^[|>][-+0-9]*(?=\s*(?:#.*)?$)/)) return 'meta';

    if (stream.match(/^"(?:[^"\\]|\\.)*"?/) || stream.match(/^'(?:[^']|'')*'?/)) return 'string';
    if (stream.match(/^[[\]{},]/)) return 'punctuation';
    if (stream.match(/^-?(?:0x[0-9a-f]+|\d+(?:\.\d+)?(?:e[-+]?\d+)?)(?=[\s,\]}]|$)/i)) return 'number';
    if (stream.match(/^(?:true|false|null|~|yes|no)(?=[\s,\]}]|$)/i)) return 'atom';

    stream.match(/^[^#,\]}]*[^\s#,\]}]/) || stream.next();
    return 'string';
  },
};

export function yaml() {
  return StreamLanguage.define(yamlParser);
}
