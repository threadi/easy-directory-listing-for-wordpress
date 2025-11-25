/**
 * Embed necessary dependencies.
 */
import './style.scss';
import { render } from "react-dom";
import { useState, useEffect } from "@wordpress/element"
import apiFetch from '@wordpress/api-fetch';
import { Button } from '@wordpress/components';
import { human_file_size } from './helper';
import {EDLFW_FORM} from "./form";
import {EDLFW_ERRORS} from "./errors";

/**
 * Define the Easy Directory Listing for WordPress.
 *
 * Requests the contents to display via REST API.
 */
const EDLFW_Directory_Viewer = ( props ) => {
    const [ enabled, setEnabled ] = useState( false );
    const [ tree, setTree ] = useState( false );
    const [ loadTree, setLoadTree ] = useState( false );
    const [ actualDirectory, setActualDirectory ] = useState( false );
    const [ actualDirectoryPath, setActualDirectoryPath ] = useState( false );
    const [ openDirectoryPath, setOpenDirectoryPath ] = useState( false );
    const [ errors, setErrors ] = useState( false );
    const [ saveCredentials, setSaveCredentials ] = useState( false );
    const [ directoriesToLoad, setDirectoriesToLoad ] = useState( 0 );
    const [ updated, setUpdated ] = useState( false );
    let [ cancelLoading, setCancelLoading ] = useState( false );

    // get configuration.
    let config = props.config;

    // bail if no configuration is set.
    if ( ! config ) {
        return (<><p>{edlfwJsVars.config_missing}</p></>)
    }

    // bail if nonce is missing.
    if ( ! config.nonce ) {
        return (<><p>{edlfwJsVars.nonce_missing}</p></>)
    }

    // if error occurred reset the term.
    if( errors ) {
        config.term = false;
        setCancelLoading = false;
    }

    // get the recursive listing for the given directory.
    useEffect( () => {
        if( ! loadTree && Object.keys(config.fields).length > 0 && directoriesToLoad === 0 ){
            return;
        }
        // collect params for request.
        let params = {
            fields: config.fields,
            listing_base_object_name: config.listing_base_object_name,
            saveCredentials: saveCredentials,
            nonce: config.nonce,
            term: config.term,
            cancelLoading: cancelLoading
        }
        apiFetch( {path: edlfwJsVars.get_directory_endpoint, method: 'POST', data: params} ).then( (response) => {
            // bail on any returning error.
            if (response.errors) {
                setErrors( response.errors );
                setEnabled( false );
                setLoadTree( false );
                return;
            }

            // if we got the directory_loading marker, trigger next request.
            if ( response.directory_loading ) {
                setDirectoriesToLoad( response.directory_to_load );
                setLoadTree( ! loadTree );
                return;
            }

            // on all other responses set the tree and show it.
            setTree( response );
            setLoadTree( false );
            setErrors( false );
        } ).catch( (err) => {
            let fetch_errors = [];
            fetch_errors.push( edlfwJsVars.serverside_error );
            fetch_errors.push( err.message );
            setErrors( fetch_errors );
            setEnabled( false );
            setLoadTree( false );
        } );
    }, [loadTree] );

    // load requested term.
    if( ! enabled && config.term ) {
        setErrors( false )
        setEnabled( true )
        setLoadTree( ! loadTree );
        return;
    }

    // show dynamic form if fields are set.
    if( ! enabled && Object.keys(config.fields).length > 0 && ! config.term ) {
        return (
            <>
                <EDLFW_FORM config={config} loadTree={loadTree} setLoadTree={setLoadTree} errors={errors} setErrors={setErrors} setEnabled={setEnabled} saveCredentials={saveCredentials} setSaveCredentials={setSaveCredentials} updated={updated} setUpdated={setUpdated} />
            </>)
    }

    // show errors.
    if( errors ) {
        return (
            <EDLFW_ERRORS errors={errors}/>
        )
    }

    // bail if directory listing is empty (we assume it is still loading).
    if( ! tree || ( tree && tree instanceof Array ) ) {
        return (
            <div className="is-loading">
                <p><span>{ edlfwJsVars.is_loading }</span> ({ directoriesToLoad > 1 && edlfwJsVars.loading_directories.replace( '%1$d', directoriesToLoad ) }{ directoriesToLoad <= 1 && edlfwJsVars.loading_directory })</p>
                {!cancelLoading && <p>{<Button variant="secondary" onClick={() => setCancelLoading(true)}>{edlfwJsVars.cancel}</Button>}</p>}
                {cancelLoading && <p>{edlfwJsVars.please_wait}</p>}
            </div>
        )
    }

    // if actual directory is not set, use the first one from result.
    if( ! actualDirectory && Object.keys(tree) && Object.keys(tree)[0] ) {
        setActualDirectory( tree[Object.keys(tree)[0]].files )
        setActualDirectoryPath( Object.keys(tree)[0] );
        setOpenDirectoryPath( Object.keys(tree)[0] )
    }

    // add class on body as marker that listing is loaded.
    document.body.classList.add('easy-directory-listing-for-wordpress-loaded');

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
                    <ul><EDLFW_Directory_Listing tree={tree} actualDirectoryPath={actualDirectoryPath} setActualDirectory={setActualDirectory} setActualDirectoryPath={setActualDirectoryPath} openDirectoryPath={openDirectoryPath} setOpenDirectoryPath={setOpenDirectoryPath} /></ul>
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
                        <EDLFW_Files_Listing directoryToList={actualDirectory} config={config} term={config.term} />
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
 * @param tree
 * @param actualDirectoryPath
 * @param setActualDirectory
 * @param setActualDirectoryPath
 * @param openDirectoryPath
 * @param setOpenDirectoryPath
 * @returns {*}
 * @constructor
 */
const EDLFW_Directory_Listing = ( { tree, actualDirectoryPath, setActualDirectory, setActualDirectoryPath, openDirectoryPath, setOpenDirectoryPath } ) => {
    // bail if no directories are given.
    if( ! tree ) {
        return '';
    }

    // change to this directory and show its files.
    function changeDirectory( directory ) {
        setActualDirectory(tree[directory].files);
        setActualDirectoryPath( directory );
        setOpenDirectoryPath( directory )
    }

    // open this directory.
    function openDirectory( directory ) {
        setOpenDirectoryPath( directory )
    }

    return (Object.keys(tree).map( directory => {
            // set button class.
            let buttonClassName = 'secondary';
            if( actualDirectoryPath === directory ) {
                buttonClassName = 'primary';
            }

            // set class for directory symbol and to show sub-directories.
            let directoryClassName = '';
            if( openDirectoryPath === directory ) {
                directoryClassName = 'open';
            }

            return (<li key={directory} className={directoryClassName}>
                    <a href="#" onClick={() => openDirectory( directory )}>&nbsp;</a>
                    <Button variant={buttonClassName}
                            onClick={() => changeDirectory( directory )}>{tree[directory].title}</Button>
                    {tree[directory].dirs &&
                        <ul><EDLFW_Directory_Listing tree={tree[directory].dirs} actualDirectoryPath={actualDirectoryPath}
                                                     setActualDirectory={setActualDirectory}
                                                     setActualDirectoryPath={setActualDirectoryPath}
                                                     openDirectoryPath={openDirectoryPath} setOpenDirectoryPath={setOpenDirectoryPath} /></ul>}
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
 * @param term
 * @returns {*}
 * @constructor
 */
const EDLFW_Files_Listing = ( { directoryToList, config, term } ) => {
    if ( ! directoryToList.length ) {
        return (<tr><td colSpan="6"><p>{edlfwJsVars.empty_directory}</p></td></tr>)
    }

    return (Object.keys(directoryToList).map( directory => {
        let file = directoryToList[directory];
        return (<tr key={file.title}>
            <td className="actions">
                {config.actions.map( action => {
                    if( typeof action.show !== 'undefined' && typeof action.hint !== 'undefined' && ! eval( action.show ) ) {
                        return (<span key={action.action} dangerouslySetInnerHTML={{__html: action.hint}}/>)
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

