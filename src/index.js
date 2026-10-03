/**
 * Embed necessary dependencies.
 */
import './style.scss';
import { render } from "react-dom";
import { useState, useEffect, useMemo } from "@wordpress/element"
import apiFetch from '@wordpress/api-fetch';
import { Button } from '@wordpress/components';
import { human_file_size, edlfw_text, edlfw_get_search_terms, edlfw_search_tree, edlfw_split_by_term } from './helper';
import {EDLFW_FORM} from "./form";
import {EDLFW_ERRORS} from "./errors";

/**
 * The minimum amount of chars to start the search in the loaded tree.
 */
const EDLFW_SEARCH_MIN_CHARS = 2;

/**
 * The max amount of search results to show.
 */
const EDLFW_SEARCH_MAX_RESULTS = 200;

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
    const [ search, setSearch ] = useState( '' );

    // search in the complete loaded tree: this does not need any further request.
    const searchTerms = useMemo( () => edlfw_get_search_terms( search, EDLFW_SEARCH_MIN_CHARS ), [search] );
    const searchData = useMemo( () => edlfw_search_tree( tree, searchTerms, EDLFW_SEARCH_MAX_RESULTS ), [tree, searchTerms] );
    const isSearching = searchTerms.length > 0;

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

    // open the directory of a search result and end the search.
    function openSearchResult( result ) {
        setActualDirectory( result.node.files );
        setActualDirectoryPath( result.directory );
        setOpenDirectoryPath( result.directory );
        setSearch( '' );
    }

    // get the summary for the actual search.
    let searchSummary = '';
    if( isSearching ) {
        if( searchData.hits === 0 ) {
            searchSummary = edlfw_text( edlfwJsVars.search_no_hits, search.trim() );
        }
        else if( searchData.hits === 1 ) {
            searchSummary = edlfw_text( edlfwJsVars.search_hit, search.trim() );
        }
        else if( searchData.hitDirectories === 1 ) {
            searchSummary = edlfw_text( edlfwJsVars.search_hits_dir, searchData.hits, search.trim() );
        }
        else {
            searchSummary = edlfw_text( edlfwJsVars.search_hits_dirs, searchData.hits, searchData.hitDirectories, search.trim() );
        }
    }

    // generate output.
    return (
        <>
            <div id="easy-directory-listing-for-wordpress-options">
                <div className="edlfw-global-actions">
                    {! isSearching && config.global_actions.map( action => {
                        return (<Button variant="primary" key={action.action} onClick={() => eval( action.action )}>{action.label}</Button>)
                    } )}
                    {isSearching && <p className="edlfw-search-summary" role="status"><strong>{searchSummary}</strong> <Button variant="link" onClick={() => setSearch( '' )}>{edlfwJsVars.search_end}</Button></p>}
                </div>
                <EDLFW_Search search={search} setSearch={setSearch} />
            </div>
            <div id="easy-directory-listing-for-wordpress-listing-view">
                <div id="easy-directory-listing-for-wordpress-listing" className={isSearching ? 'is-searching' : ''}>
                    <ul><EDLFW_Directory_Listing tree={tree} actualDirectoryPath={actualDirectoryPath} setActualDirectory={setActualDirectory} setActualDirectoryPath={setActualDirectoryPath} openDirectoryPath={openDirectoryPath} setOpenDirectoryPath={setOpenDirectoryPath} searchData={isSearching ? searchData : false} setSearch={setSearch} /></ul>
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
                        <EDLFW_Files_Listing directoryToList={isSearching ? searchData.results : actualDirectory} config={config} term={config.term} searchData={isSearching ? searchData : false} searchTerms={searchTerms} search={search} setSearch={setSearch} openSearchResult={openSearchResult} />
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    )
}

/**
 * Show the search field to search for files in the complete loaded tree.
 *
 * @param search
 * @param setSearch
 * @returns {JSX.Element}
 * @constructor
 */
const EDLFW_Search = ( { search, setSearch } ) => {
    return (
        <div className="edlfw-search">
            <label className="screen-reader-text" htmlFor="edlfw-search-input">{edlfwJsVars.search_label}</label>
            <span className="dashicons dashicons-search" aria-hidden="true"></span>
            <input
                id="edlfw-search-input"
                type="search"
                autoComplete="off"
                value={search}
                placeholder={edlfwJsVars.search_placeholder}
                onChange={( event ) => setSearch( event.target.value )}
                onKeyDown={( event ) => {
                    if( event.key === 'Escape' ) {
                        setSearch( '' );
                    }
                }}
            />
            {search.length > 0 && <button type="button" className="edlfw-search-clear" aria-label={edlfwJsVars.search_clear} title={edlfwJsVars.search_clear} onClick={() => setSearch( '' )}><span className="dashicons dashicons-no-alt" aria-hidden="true"></span></button>}
            {search.trim().length > 0 && search.trim().length < EDLFW_SEARCH_MIN_CHARS && <p className="edlfw-search-hint">{edlfw_text( edlfwJsVars.search_min_chars, EDLFW_SEARCH_MIN_CHARS )}</p>}
        </div>
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
 * @param searchData
 * @param setSearch
 * @returns {*}
 * @constructor
 */
const EDLFW_Directory_Listing = ( { tree, actualDirectoryPath, setActualDirectory, setActualDirectoryPath, openDirectoryPath, setOpenDirectoryPath, searchData, setSearch } ) => {
    // bail if no directories are given.
    if( ! tree ) {
        return '';
    }

    // change to this directory and show its files.
    function changeDirectory( directory ) {
        setActualDirectory(tree[directory].files);
        setActualDirectoryPath( directory );
        setOpenDirectoryPath( directory )

        // end the search to show the files of this directory.
        if( searchData ) {
            setSearch( '' );
        }
    }

    // open this directory.
    function openDirectory( directory ) {
        setOpenDirectoryPath( directory )
    }

    return (Object.keys(tree).map( directory => {
            // set button class.
            let buttonClassName = '';
            if( ! searchData && actualDirectoryPath === directory ) {
                buttonClassName = 'primary';
            }

            // set class for directory symbol and to show sub-directories.
            let directoryClassName = '';
            if( openDirectoryPath === directory ) {
                directoryClassName = 'open';
            }

            // during a search: mark the directories with hits and show the amount of hits in this directory.
            let hits = 0;
            if( searchData ) {
                hits = searchData.counts[directory] ?? 0;
                directoryClassName += ( searchData.subtree[directory] ?? 0 ) > 0 ? ' has-results' : ' no-results';
                if( hits === 0 ) {
                    directoryClassName += ' no-own-results';
                }
            }

            return (<li key={directory} className={directoryClassName.trim()}>
                    <a href="#" onClick={() => openDirectory( directory )}>&nbsp;</a>
                    <Button variant={buttonClassName}
                            onClick={() => changeDirectory( directory )}>{tree[directory].title}</Button>
                    {hits > 0 && <span className="edlfw-search-count">{hits}</span>}
                    {tree[directory].dirs &&
                        <ul><EDLFW_Directory_Listing tree={tree[directory].dirs} actualDirectoryPath={actualDirectoryPath}
                                                     setActualDirectory={setActualDirectory}
                                                     setActualDirectoryPath={setActualDirectoryPath}
                                                     openDirectoryPath={openDirectoryPath} setOpenDirectoryPath={setOpenDirectoryPath}
                                                     searchData={searchData} setSearch={setSearch} /></ul>}
                </li>
            )
        }
    ))
}

/**
 * Show files in given directory or, during a search, the files found in the complete tree.
 *
 * @param directoryToList
 * @param config
 * @param term
 * @param searchData
 * @param searchTerms
 * @param search
 * @param setSearch
 * @param openSearchResult
 * @returns {*}
 * @constructor
 */
const EDLFW_Files_Listing = ( { directoryToList, config, term, searchData, searchTerms, search, setSearch, openSearchResult } ) => {
    // show hint if the search does not have any hit.
    if ( searchData && ! directoryToList.length ) {
        return (<tr className="edlfw-search-empty"><td colSpan="6">
            <p><strong>{edlfw_text( edlfwJsVars.search_no_hits, search.trim() )}</strong></p>
            <p>{edlfw_text( edlfwJsVars.search_scope, searchData.files, searchData.directories )}</p>
            <p><Button variant="link" onClick={() => setSearch( '' )}>{edlfwJsVars.search_reset}</Button></p>
        </td></tr>)
    }

    if ( ! directoryToList.length ) {
        return (<tr><td colSpan="6"><p>{edlfwJsVars.empty_directory}</p></td></tr>)
    }

    // get the parts of the text around the link to the directory of a search result.
    const inDirectory = String( edlfwJsVars.search_in_directory ?? '%1$s' ).split( '%1$s' );

    const rows = Object.keys(directoryToList).map( directory => {
        // during a search each entry contains the file and the directory where it has been found.
        let searchResult = searchData ? directoryToList[directory] : false;
        let file = searchResult ? searchResult.file : directoryToList[directory];
        let titleParts = edlfw_split_by_term( file.title, searchResult ? searchTerms[0] : '' );
        return (<tr key={( searchResult ? searchResult.directory : '' ) + file.title}>
            <td className="actions">
                {config.actions.map( action => {
                    if( typeof action.show !== 'undefined' && typeof action.hint !== 'undefined' && ! eval( action.show ) ) {
                        return (<span key={action.action} dangerouslySetInnerHTML={{__html: action.hint}}/>)
                    }
                    return (<Button key={action.action} onClick={() => eval( action.action )}>{action.label}</Button>)
                } )}
            </td>
            <td className="filepreview"><span dangerouslySetInnerHTML={{__html: file.preview}} /></td>
            <td className="filename">
                {titleParts.before}{titleParts.match.length > 0 && <mark>{titleParts.match}</mark>}{titleParts.after}
                {searchResult && <span className="edlfw-search-path">{inDirectory[0]}<Button variant="link" onClick={() => openSearchResult( searchResult )}>{searchResult.label}</Button>{inDirectory[1] ?? ''}</span>}
            </td>
            <td className="date">{file['last-modified']}</td>
            <td className="type"><span dangerouslySetInnerHTML={{__html: file.icon}} /></td>
            <td className="filesize">{human_file_size( file.filesize )}</td>
        </tr>)
    } );

    // show hint if not all hits are shown.
    if ( searchData && searchData.hits > directoryToList.length ) {
        rows.push( <tr key="edlfw-search-limited" className="edlfw-search-limited"><td colSpan="6"><p>{edlfw_text( edlfwJsVars.search_limited, directoryToList.length, searchData.hits )}</p></td></tr> );
    }

    return rows;
}

/**
 * Initialize the rendering for the directory listing viewer.
 */
function edfw_add_directory_view() {
    // get object.
    let obj = document.getElementById('easy-directory-listing-for-wordpress')

    // bail if config is not set.
    if( ! obj || ! obj.dataset.config ) {
        return;
    }

    // remove the inner content.
    obj.innerHTML = '';

    // get the configuration.
    let config = JSON.parse(obj.dataset.config);

    if( ReactDOM.createRoot === undefined ) {
        // old style way: use render.
        const container = document.getElementById('easy-directory-listing-for-wordpress');
        render(<EDLFW_Directory_Viewer config={config}/>, container);
    }
    else {
        // modern way: use createRoot.
        let edfw_directory = ReactDOM.createRoot(document.getElementById('easy-directory-listing-for-wordpress'));
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

