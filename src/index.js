/**
 * Embed necessary dependencies.
 */
import './style.scss';
import { render } from "react-dom";
import { useState, useEffect } from "@wordpress/element"
import apiFetch from '@wordpress/api-fetch';
import { Button } from '@wordpress/components';
import { human_file_size } from './helper';
import {EDLFW_LOGIN_FORM} from "./forms/login_form";
import {EDLFW_SIMPLE_API_FORM} from "./forms/simple_api";
import {EDLFW_FILE_FORM} from "./forms/file";
import {EDLFW_ERRORS} from "./errors";

/**
 * Define the Easy Directory Listing for WordPress.
 *
 * Using:
 * - URL / Path to main directory
 * - URL / Path to active directory which should be visible
 * - Login (optional)
 * - Password (optional)
 *
 * Requests the contents to display via REST API.
 */
const EDLFW_Directory_Viewer = ( props ) => {
  const [ enabled, setEnabled ] = useState( false );
  const [ tree, setTree ] = useState( false );
  const [ actualDirectory, setActualDirectory ] = useState( false );
  const [ actualDirectoryPath, setActualDirectoryPath ] = useState( false );
  const [ url, setUrl ] = useState( props.config.directory );
  const [ login, setLogin ] = useState( '' );
  const [ password, setPassword ] = useState( '' );
  const [ apiKey, setApiKey ] = useState( '' );
  const [ errors, setErrors ] = useState( false );
  const [ saveCredentials, setSaveCredentials ] = useState( false );
  const [ loadTree, setLoadTree ] = useState( true );
  const [ directoryToLoad, setDirectoryToLoad ] = useState( 0 );

  // get configuration.
  let config = props.config;

  // bail if no configuration is set.
  if (!config) {
    return (<><p>{edlfwJsVars.config_missing}</p></>)
  }

  // bail if nonce is missing
  if (!config.nonce) {
    return (<><p>{edlfwJsVars.nonce_missing}</p></>)
  }

  // if error occurred reset the term.
  if( errors ) {
    config.term = false;
  }

  // get the recursive listing for the given directory.
  useEffect( () => {
    // collect params for request.
    let params = {
      directory: url,
      login: login,
      password: password,
      api_key: apiKey,
      listing_base_object_name: config.listing_base_object_name,
      saveCredentials: saveCredentials,
      nonce: config.nonce,
      term: config.term
    }
    apiFetch( { path: edlfwJsVars.get_directory_endpoint, method: 'POST', data: params } ).then( ( response ) => {
      // bail on any returning error.
      if( response.errors ) {
        setErrors(response.errors);
        setEnabled( false );
        return;
      }

      // if we got the directory_loading marker, send next request.
      if( response.directory_loading ) {
        setLoadTree( ! loadTree );
        setDirectoryToLoad( response.directory_to_load )
        return;
      }

      // on all other responses set the tree and show it.
      setTree( response );
      setErrors( false );
    } ).catch( ( err ) => {
      let fetch_errors = new Array();
      fetch_errors.push( edlfwJsVars.serverside_error );
      fetch_errors.push( err.message );
      setErrors( fetch_errors );
      setEnabled( false );
    } );
  }, [loadTree] );

  // show login form if directory is not enabled and login should be requested.
  if( ! enabled && config.requires_login && ! config.term ) {
    return (
        <>
          <EDLFW_LOGIN_FORM config={config} errors={errors} url={url} setUrl={setUrl} login={login} setLogin={setLogin} password={password} setPassword={setPassword} setEnabled={setEnabled} saveCredentials={saveCredentials} setSaveCredentials={setSaveCredentials} />
        </>)
  }

  // show API form if directory is not enabled and API should be requested.
  if( ! enabled && config.requires_simple_api && ! config.term ) {
    return (
        <>
          <EDLFW_SIMPLE_API_FORM config={config} errors={errors} apiKey={apiKey} setApiKey={setApiKey} setEnabled={setEnabled} url={url} setUrl={setUrl} saveCredentials={saveCredentials} setSaveCredentials={setSaveCredentials} />
        </>)
  }

  // show simple directory form.
  if( ! enabled && ! config.directory && ! config.term ) {
    return (
        <>
          <EDLFW_FILE_FORM errors={errors} apiKey={apiKey} setApiKey={setApiKey} setEnabled={setEnabled} url={url} setUrl={setUrl} saveCredentials={saveCredentials} setSaveCredentials={setSaveCredentials} />
        </>)
  }

  // show errors.
  if( errors ) {
    return (
        <EDLFW_ERRORS errors={errors}/>
    )
  }

  // bail if directory listing is empty (we assume it is still loading).
  if( ! tree ) {
    return (
        <p className="is-loading">{ edlfwJsVars.is_loading } ({ edlfwJsVars.loading_directories.replace( '%1$d', directoryToLoad ) })</p>
    )
  }

  // if actual directory is not set, use the first one from result.
  if( ! actualDirectory ) {
    setActualDirectory( tree[Object.keys(tree)[0]].files )
  }

  // generate output.
  return (
      <>
        <div id="easy-directory-listing-for-wordpress-options">
          {config.global_actions.map( action => {
            return (<Button variant="primary" key={action.action} onClick={() => eval( action.action )}>{action.label}</Button>)
          } )}
        </div>
        <div id="easy-directory-listing-for-wordpress-listing-view">
          <div id="easy-directory-listing-for-wordpress-listing">
            <ul><EDLFW_Directory_Listing tree={tree} setActualDirectory={setActualDirectory} setActualDirectoryPath={setActualDirectoryPath} /></ul>
          </div>
          <div id="easy-directory-listing-for-wordpress-details">
            <table className="wp-list-table widefat fixed striped table-view-list">
              <thead>
              <tr>
                <th className="actions">{edlfwJsVars.actions}</th>
                <th className="filepreview">&nbsp;</th>
                <th className="filename">{edlfwJsVars.filename}</th>
                <th className="date">{edlfwJsVars.date}</th>
                <th className="type">&nbsp;</th>
                <th className="filesize">{edlfwJsVars.filesize}</th>
              </tr>
              </thead>
              <tbody>
              <EDLFW_Files_Listing directoryToList={actualDirectory} config={config} url={url} login={login} password={password} term={config.term} />
              </tbody>
            </table>
          </div>
        </div>
      </>
  )
}

/**
 * Show recursive directory listings.
 *
 * @param directoryToList
 * @param setActualDirectory
 * @param setActiveDirectoryPath
 * @returns {*}
 * @constructor
 */
const EDLFW_Directory_Listing = ( { tree, setActualDirectory, setActualDirectoryPath } ) => {
  // bail if no directories are given.
  if( ! tree ) {
    return '';
  }

  function changeDirectory( directory ) {
    setActualDirectory(tree[directory].files);
    setActualDirectoryPath( directory );
  }

  return (Object.keys(tree).map( directory => {
        return (<li key={directory}>
              <Button onClick={() => changeDirectory(directory)}>{tree[directory].title}</Button>
              {tree[directory].dirs && <ul><EDLFW_Directory_Listing tree={tree[directory].dirs} setActualDirectory={setActualDirectory} setActualDirectoryPath={setActualDirectoryPath} /></ul>}
            </li>
        )
      }
  ))
}

/**
 * Show files in given directory.
 *
 * @param directoryToList
 * @param config
 * @param url
 * @param login
 * @param password
 * @param term
 * @returns {*}
 * @constructor
 */
const EDLFW_Files_Listing = ( { directoryToList, config, url, login, password, term } ) => {
  return (Object.keys(directoryToList).map( directory => {
    let file = directoryToList[directory];
    return (<tr key={file.title}>
      <td className="actions">
        {config.actions.map( action => {
          if( typeof action.show !== 'undefined' && typeof action.hint !== 'undefined' && ! eval( action.show ) ) {
            return action.hint;
          }
          return (<Button key={action.action} onClick={() => eval( action.action )}>{action.label}</Button>)
        } )}
      </td>
      <td className="filepreview"><span dangerouslySetInnerHTML={{__html: file.preview}} /></td>
      <td className="filename">{file.title}</td>
      <td className="date">{file['last-modified']}</td>
      <td className="type"><span dangerouslySetInnerHTML={{__html: file.icon}} /></td>
      <td className="filesize">{human_file_size( file.filesize )}</td>
    </tr>)
  } ))
}

/**
 * Initialize the rendering for the directory listing viewer.
 */
function edfw_add_directory_view() {
  // get object.
  let obj = top.document.getElementById('easy-directory-listing-for-wordpress')

  // bail if config is not set.
  if( ! obj || ! obj.dataset.config ) {
    return;
  }

  // get the configuration.
  let config = JSON.parse(obj.dataset.config);

  if( ReactDOM.createRoot === undefined ) {
    // old style way: use render.
    const container = top.document.getElementById('easy-directory-listing-for-wordpress');
    render(<EDLFW_Directory_Viewer config={config}/>, container);
  }
  else {
    // modern way: use createRoot.
    let edfw_directory = ReactDOM.createRoot(top.document.getElementById('easy-directory-listing-for-wordpress'));
    edfw_directory.render(
        <EDLFW_Directory_Viewer config={config}/>
    );
  }
}

/**
 * Add events where the dialog could be fired.
 */
document.addEventListener( 'DOMContentLoaded', () => {
  /**
   * Add listener which could be used to trigger the directory listing with given configuration.
   *
   * Example: document.body.dispatchEvent( new CustomEvent( "easy-directory-listing-for-wordpress" ) );
   */
  document.body.addEventListener('easy-directory-listing-for-wordpress', function() {
    edfw_add_directory_view();
  });

  document.body.dispatchEvent( new CustomEvent( "easy-directory-listing-for-wordpress" ) );
})

