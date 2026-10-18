/**
 * Format bytes as human-readable text.
 *
 * @param bytes Number of bytes.
 * @param si True to use metric (SI) units, aka powers of 1000. False to use
 *           binary (IEC), aka powers of 1024.
 * @param dp Number of decimal places to display.
 *
 * @source https://stackoverflow.com/questions/10420352/converting-file-size-in-bytes-to-human-readable-string
 *
 * @return Formatted string.
 */
export const human_file_size = ( bytes, si= false, dp= 1 ) => {
  const thresh = si ? 1000 : 1024;

  if (Math.abs(bytes) < thresh) {
    return bytes + ' B';
  }

  const units = si
    ? ['kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
    : ['KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
  let u = -1;
  const r = 10**dp;

  do {
    bytes /= thresh;
    ++u;
  } while (Math.round(Math.abs(bytes) * r) / r >= thresh && u < units.length - 1);


  return bytes.toFixed(dp) + ' ' + units[u];
}

/**
 * Replace the placeholders in a translated text (e.g. %1$d or %2$s) with the given values.
 *
 * @param text The text with placeholders.
 * @param values The values in the order of their placeholder number.
 *
 * @return The resulting text.
 */
export const edlfw_text = ( text, ...values ) => {
  let result = String( text ?? '' );
  values.forEach( ( value, index ) => {
    result = result.split( '%' + ( index + 1 ) + '$d' ).join( String( value ) ).split( '%' + ( index + 1 ) + '$s' ).join( String( value ) );
  } );
  return result;
}

/**
 * Return the list of search terms from the given search string.
 *
 * The list is empty if the search string is too short. Each term is in lower case.
 *
 * @param search The search string as entered by the user.
 * @param minChars The minimum amount of chars the search string must have.
 *
 * @return List of terms.
 */
export const edlfw_get_search_terms = ( search, minChars = 2 ) => {
  const trimmed = String( search ?? '' ).trim().toLowerCase();
  if ( trimmed.length < minChars ) {
    return [];
  }
  return trimmed.split( /\s+/ );
}

/**
 * Search in the complete loaded directory tree for files, whose name contains all given terms.
 *
 * The tree is an object with the directory path as key. Each entry contains "title", "files" (list of files)
 * and "dirs" (object of sub-directories in the same format).
 *
 * @param tree The loaded tree.
 * @param terms List of search terms in lower case.
 * @param maxResults The max amount of results to return.
 *
 * @return Object with:
 * - results: list of { file, directory, label, node }, limited to maxResults
 * - counts: amount of hits per directory (without its sub-directories), keyed by directory path
 * - subtree: amount of hits per directory incl. its sub-directories, keyed by directory path
 * - hits: total amount of hits
 * - hitDirectories: amount of directories with at least one hit
 * - files: total amount of files in the tree
 * - directories: total amount of directories in the tree
 */
export const edlfw_search_tree = ( tree, terms, maxResults = 200 ) => {
  const data = {
    results: [],
    counts: {},
    subtree: {},
    hits: 0,
    hitDirectories: 0,
    files: 0,
    directories: 0
  };

  // bail if the tree is not usable (e.g., still loading).
  if ( ! tree || typeof tree !== 'object' || Array.isArray( tree ) ) {
    return data;
  }

  const walk = ( nodes, parents ) => {
    let sum = 0;
    Object.keys( nodes ).forEach( path => {
      const node = nodes[path];

      // bail if this is not a directory entry.
      if ( ! node || typeof node !== 'object' ) {
        return;
      }
      data.directories++;

      // get the label for this directory, relative to the first directory of the tree.
      const titles = parents.concat( [ String( node.title ?? '' ) ] );
      const label = titles.length > 1 ? '/' + titles.slice( 1 ).join( '/' ) + '/' : '/';

      // check the files in this directory.
      const files = Array.isArray( node.files ) ? node.files : [];
      data.files += files.length;
      let own = 0;
      if ( terms.length > 0 ) {
        files.forEach( file => {
          const title = String( ( file && file.title ) ?? '' ).toLowerCase();
          if ( ! terms.every( term => title.includes( term ) ) ) {
            return;
          }
          own++;
          if ( data.results.length < maxResults ) {
            data.results.push( { file: file, directory: path, label: label, node: node } );
          }
        } );
      }

      // check the sub-directories.
      const sub = node.dirs && typeof node.dirs === 'object' ? walk( node.dirs, titles ) : 0;

      // save the results for this directory.
      data.counts[path] = own;
      data.subtree[path] = own + sub;
      data.hits += own;
      if ( own > 0 ) {
        data.hitDirectories++;
      }
      sum += own + sub;
    } );
    return sum;
  };
  walk( tree, [] );

  return data;
}

/**
 * Split a file name in 3 parts to mark the first occurrence of the given term.
 *
 * @param title The file name.
 * @param term The term in lower case.
 *
 * @return Object with "before", "match" and "after".
 */
export const edlfw_split_by_term = ( title, term ) => {
  const text = String( title ?? '' );
  const position = term ? text.toLowerCase().indexOf( term ) : -1;
  if ( position < 0 ) {
    return { before: text, match: '', after: '' };
  }
  return {
    before: text.slice( 0, position ),
    match: text.slice( position, position + term.length ),
    after: text.slice( position + term.length )
  };
}


/**
 * Return whether the given form field has a value.
 *
 * A field of the type "file" also counts as filled if a file has been chosen, which is not uploaded yet.
 *
 * @param field The field configuration.
 * @param pendingFile The chosen file of a field of the type "file", which is not uploaded yet.
 *
 * @return {boolean} True if the field has a value.
 */
export const edlfw_is_field_filled = ( field, pendingFile = false ) => {
  // bail if no field is given.
  if ( ! field ) {
    return false;
  }

  // a chosen, but not uploaded file counts as value.
  if ( 'file' === field.type && pendingFile ) {
    return true;
  }

  // bail if no value is set.
  if ( typeof field.value === 'undefined' || field.value === null || field.value === false ) {
    return false;
  }

  // a checked checkbox has a value.
  if ( field.value === true ) {
    return true;
  }

  // all other values are checked as string (e.g. the ID of an uploaded file is a number).
  return String( field.value ).length > 0;
}

/**
 * Check the groups of fields, where exactly one field must be filled (fields with the same "one_of" value).
 *
 * @param fields The list of fields.
 * @param pendingFiles The chosen files of fields of the type "file", which are not uploaded yet (field name as key).
 *
 * @return {{missing: string[], multiple: string[]}} The names of the groups without any and with more than one filled field.
 */
export const edlfw_check_field_groups = ( fields, pendingFiles = {} ) => {
  // collect the count of filled fields per group.
  const groups = {};
  Object.keys( fields ?? {} ).forEach( name => {
    const field = fields[name];

    // bail if this field is not part of a group.
    if ( ! field || ! field.one_of ) {
      return;
    }

    // initialize the counter for this group.
    if ( typeof groups[field.one_of] === 'undefined' ) {
      groups[field.one_of] = 0;
    }

    // count this field if it is filled.
    if ( edlfw_is_field_filled( field, pendingFiles[name] ?? false ) ) {
      groups[field.one_of]++;
    }
  } );

  // return the groups without filled field and with more than one filled field.
  return {
    missing: Object.keys( groups ).filter( group => groups[group] === 0 ),
    multiple: Object.keys( groups ).filter( group => groups[group] > 1 )
  };
}

/**
 * Return whether all necessary fields of the form are filled.
 *
 * Fields with "not_required" are optional. From fields with the same "one_of" value exactly one
 * must be filled, but this check only requires at least one (more than one results in an error message on submit).
 *
 * @param fields The list of fields.
 * @param pendingFiles The chosen files of fields of the type "file", which are not uploaded yet (field name as key).
 *
 * @return {boolean} True if the form could be submitted.
 */
export const edlfw_is_form_completed = ( fields, pendingFiles = {} ) => {
  // check each required field, which is not part of a group.
  const completed = Object.keys( fields ?? {} ).every( name => {
    const field = fields[name];

    // optional fields and fields of a group are not checked here.
    if ( ! field || field.not_required || field.one_of ) {
      return true;
    }

    // return whether this field is filled.
    return edlfw_is_field_filled( field, pendingFiles[name] ?? false );
  } );

  // return the result incl. the check of the groups.
  return completed && edlfw_check_field_groups( fields, pendingFiles ).missing.length === 0;
}
