import React, {useEffect, useRef, useState} from 'react';

import {Inertia} from "@inertiajs/inertia";
import { useForm, usePage } from "@inertiajs/inertia-react";
import {useSelector} from "react-redux";
import {transformDate} from "../../../utils/date";
import axios from "axios";
import FroalaEditor from 'react-froala-wysiwyg';
import FlashMessages from "../../../common/FlashMessages";
import AppLayout from "../../../layouts/app-layout/AppLayout";
import BreadcumbComponent from "../../../common/breadcumb/Breadcumb";
import { Alert } from "react-bootstrap";

import {FROALA_KEY} from "../../../constants";

import 'froala-editor/js/froala_editor.pkgd.min.js';
import 'froala-editor/js/plugins/table.min.js';
import 'froala-editor/js/plugins/paragraph_format.min.js';
import 'froala-editor/js/plugins/align.min.js';
import 'froala-editor/js/plugins/lists.min.js';
import 'froala-editor/js/plugins/colors.min.js';
import 'froala-editor/js/plugins/image.min.js';
import 'froala-editor/js/plugins/save.min.js';

import 'froala-editor/css/froala_style.min.css';
import 'froala-editor/css/froala_editor.pkgd.min.css';
import 'froala-editor/css/plugins/table.min.css';
import route from 'ziggy-js';

const breadcrumbs = {
    title: 'View Document',
    breadcumbs: [
        {
            title: 'Documents',
            href: route('policy-management.policies')
        },
        {
            title: 'Show',
            href: ''
        }
    ]
};

const Show = () => {
    const [mode, setMode] = useState(null);
    const [versions, setVersions] = useState([]);
    const [selectedVersion, setSelectedVersion] = useState(null);
    const [controlDocument, setControlDocument] = useState(null);
    const [showAlert, setShowAlert] = useState(false);
    const [editorContentChanged, setEditorContentChanged] = useState(false);
    const [formIsSubmitting, setFormIsSubmitting] = useState(false);
    const [buttonDisabled, setButtonDisabled] = useState(false);
    const [loading, setLoading] = useState(false);

    const {document_template_id, from: fromUrl, target_data_scope: data_scope} = usePage().props;
    const from = useRef(fromUrl);
    const currentDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);
    const {data, setData, processing, post, errors} = useForm({
        title: '',
        body: '',
        description: '',
        selectedVersion: '',
        data_scope
    });

    const [topOffset,setTopOffset]=useState(window.innerWidth>1200 ? 150 : window.innerWidth>991 ? 240 : 70);

        window.addEventListener('resize',()=>{
            if(window.innerWidth>1200){
                setTopOffset(150);
            }
            else if(window.innerWidth>992){
                setTopOffset(240);
            }
            else if(window.innerWidth<=991){
                setTopOffset(70);
            }
            else{
                setTopOffset(70);
            }
        })

    useEffect(() => {
        localStorage.setItem('documentRedirectBack',fromUrl);
        fetchData();
    },[]);

    useEffect(() => {
        window.addEventListener('beforeunload', preventTabClosing);
        return () => window.removeEventListener('beforeunload',preventTabClosing);
    },[editorContentChanged,formIsSubmitting]);

    const preventTabClosing = (e) => {
        if(!formIsSubmitting && editorContentChanged){
            e.preventDefault();
            e.returnValue = '';
        }
    }

    useEffect(() => {
        if(editorContentChanged){
            const removeBeforeEventListener = Inertia.on('before', e => {
                    // user is going somewhere, allow save & restore
                    if (![route('documents.draft',document_template_id), route('documents.publish',document_template_id), route('froala.autosave',document_template_id), route('froala.remove-autosaved-content',document_template_id), route('documents.show',document_template_id), route('documents.destroy',document_template_id)].includes(e.detail.visit.url.href)) {
                        e.preventDefault();
                        AlertBox(
                            {
                                title: "Are you sure?",
                                text: "You have unsaved changes in editor that will be lost.",
                                showCancelButton: true,
                                confirmButtonColor: "#f1556c",
                                confirmButtonText: "Continue",
                                cancelButtonText: 'Cancel',
                                icon:'warning',
                                iconColor:'#f1556c'
                            },
                            function (confirmed) {
                                if (confirmed.value) {
                                    removeBeforeEventListener();
                                    Inertia.get(e.detail.visit.url.href);
                                }
                            }
                        );
                    }
                });
    
            return removeBeforeEventListener;
        }
    },[editorContentChanged]);

    const fetchData = () => {
        setLoading(true);
        axios
            .get(route('documents.get-json-data', [document_template_id]), {
                params: {data_scope}
            })
            .then(({data: {control_document, versions}}) => {
                setData(previousData => ({
                    ...previousData,
                    title: control_document.title,
                    body: control_document.body,
                    description: control_document.description,
                    selectedVersion: control_document.version
                }));
                setSelectedVersion(control_document.version);
                setControlDocument(control_document);
                setVersions(versions);
                setLoading(false);
                if(control_document.auto_saved_content){
                    setShowAlert(true);
                }else{
                    setShowAlert(false);
                }
            });
    }

    const handleChangeVersion = (version) => {
        if(selectedVersion !== version && editorContentChanged){
            AlertBox(
                {
                    title: "Are you sure?",
                    text: "You have unsaved changes in editor that will be lost.",
                    showCancelButton: true,
                    confirmButtonColor: "#f1556c",
                    confirmButtonText: "Continue",
                    cancelButtonText: 'Cancel',
                    icon:'warning',
                    iconColor:'#f1556c'
                },
                function (confirmed) {
                    if (confirmed.value) {
                        autoSaveContent();
                        setEditorContentChanged(false);
                        fetchVersionJsonData(version);
                    }
                }
            );
        }else{
            fetchVersionJsonData(version);
        }
    }

    const fetchVersionJsonData = (version) => {
        setLoading(true);
        axiosFetch(route('documents.get-json-data', [document_template_id]), {
            params: {
                version
            }
        })
            .then(({data: {control_document}}) => {
                setSelectedVersion(control_document.version);
                setData(previousData => ({
                    ...previousData,
                    title: control_document.title,
                    body: control_document.body,
                    description: control_document.description,
                    selectedVersion: control_document.version
                }));
                setControlDocument(control_document);
                setLoading(false);
                if(control_document.auto_saved_content){
                    setShowAlert(true);
                }else{
                    setShowAlert(false);
                }
            });
    }

    const handleEditorChange = (value) => {
        setData('body', value);
        setEditorContentChanged(true);
    }

    useEffect(() => {
        const timer = setTimeout(() => {
            if(selectedVersion && editorContentChanged){
                autoSaveContent();
            }
        },2500);

        return () => {
            clearTimeout(timer);
        }
    },[data.body,editorContentChanged]);

    const autoSaveContent = () => {
        axios.post(route('froala.autosave'), {
            id: document_template_id,
            selectedVersion: selectedVersion,
            body: data.body,
        });
    }

    const restoreAutoSavedContent = () => {
        setData(previousData => ({
            ...previousData,
            body: controlDocument.auto_saved_content,
        }));
        setShowAlert(false);
    }

    const removeAutoSavedContent = () => {
        axios.post(route('froala.remove-autosaved-content'), {
            id: document_template_id,
            selectedVersion: selectedVersion
        }).then(res => {
            if(res.data.success){
                setShowAlert(false);
            }
        });
    }

    const handleSubmit = (e) => {
        e.preventDefault();

        setFormIsSubmitting(true);

        let url = route('documents.draft', [document_template_id]);
        if ('publish' === mode) url = route('documents.publish', [document_template_id]);

        post(url, {
            onSuccess: () => {
                removeAutoSavedContent();
                setSelectedVersion(null);
                fetchData();
                setFormIsSubmitting(false);
                setEditorContentChanged(false);
            },
            preserveState: false
        });
    };

    const dataScopeRef = useRef(currentDataScope);
    useEffect(() => {
        if (dataScopeRef.current !== currentDataScope) {
            Inertia.get(route("policy-management.policies"));
        }
    }, [currentDataScope]);

    const redirectBack = () => {
        let prevPage = localStorage.getItem('documentRedirectBack')
        
        if(editorContentChanged){
            AlertBox(
                {
                    title: "Are you sure?",
                    text: "You have unsaved changes in editor that will be lost.",
                    showCancelButton: true,
                    confirmButtonColor: "#f1556c",
                    confirmButtonText: "Continue",
                    cancelButtonText: 'Cancel',
                    icon:'warning',
                    iconColor:'#f1556c'
                },
                function (confirmed) {
                    if (confirmed.value) {
                        setEditorContentChanged(false);
                        Inertia.get(prevPage);
                    }
                }
            );
        }

        Inertia.get(prevPage);
    }

    return (
        <AppLayout>
            <BreadcumbComponent data={breadcrumbs}/>
            <FlashMessages/>
            <div className="row">
                <div className="col-md-3">
                    <div className="card">
                        <div className="card-body">
                            <div>
                                {versions.map((version, index) => (
                                    <button
                                        key={index}
                                        onClick={() => handleChangeVersion(version)}
                                        className={`btn rounded-pill fw-bold mb-1 btn-xs me-1 ${selectedVersion === version ? 'btn-primary' : 'btn-secondary'}`}
                                    >
                                        v{version}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                    <div className="card mt-1">
                        <div className="card-body">
                            <ul className="p-0 mb-0">
                                <li className="d-flex justify-content-between align-items-center">
                                    <strong>Owner:</strong>
                                    <span>{controlDocument?.admin_id ? controlDocument.admin.full_name : '-'}</span>
                                </li>
                                <li className="d-flex justify-content-between align-items-center">
                                    <strong>Status:</strong>
                                    <span className="badge bg-info">{controlDocument?.status.toUpperCase()}</span>
                                </li>
                                <li className="d-flex justify-content-between align-items-center">
                                    <strong>Created at:</strong>
                                    <span>{transformDate(controlDocument?.created_at)}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div className="col-md-9">
                    <div className="d-flex flex-row-reverse mb-2">
                        <button onClick={() => redirectBack()} className="btn btn-sm btn-danger">Go back</button>
                    </div>
                            {showAlert && <Alert variant={"warning d-flex align-items-center justify-content-between"}>
                                <span>
                                    <i className="fas fa-info-circle flex-shrink-0 me-1"/>
                                    <span>There is an autosave of this document that is more recent than the version below.</span>
                                </span>
                                <span>
                                <a href='#' onClick={() => restoreAutoSavedContent()} className={'btn btn-xs btn-primary'}>Restore autosave</a>
                                <a href='#' onClick={() => removeAutoSavedContent()} className={'fas fa-times fa-lg'} style={{'marginLeft':'10px','color':'#6c757d'}}></a>
                                </span>
                            </Alert>}

                    <div className="card">
                        <div className="card-body">
                            <form onSubmit={handleSubmit}>
                                <div className="form-group">
                                    <label htmlFor="title" className="form-label">Title</label>
                                    <input
                                        id="title"
                                        value={data.title}
                                        className="form-control"
                                        onChange={e => setData('title', e.target.value)}
                                    />
                                    {errors.title && (
                                        <div className="invalid-feedback d-block">
                                            {errors.title}
                                        </div>
                                    )}
                                </div>

                                <div className="form-group mt-2">
                                    <label htmlFor="description" className="form-label">Description</label>
                                    <textarea
                                        id="description"
                                        value={data.description}
                                        onChange={e => setData('description', e.target.value)}
                                        className="form-control"
                                    />
                                    {errors.description && (
                                        <div className="invalid-feedback d-block">
                                            {errors.description}
                                        </div>
                                    )}
                                </div>

                                <div className="mt-2">
                                    <FroalaEditor
                                        config={{
                                            key: FROALA_KEY,
                                            attribution: false,
                                            paragraphFormatSelection: true,
                                            toolbarButtons: ['undo', 'redo', '|', 'paragraphFormat', '|', 'bold', 'italic', 'textColor', 'backgroundColor', 'clearFormatting', '|', 'formatUL', 'formatOL', '|', 'alignLeft', 'alignCenter', 'alignRight', '|', 'insertTable', 'insertImage', 'restoreAutoSave'],
                                            paragraphFormat: {
                                                N: 'Normal',
                                                H4: 'Heading 4',
                                                H3: 'Heading 3',
                                                H2: 'Heading 2',
                                                H1: 'Heading 1',
                                            },
                                            toolbarSticky: true,
                                            toolbarStickyOffset: topOffset,
                                            colorsBackground: ['#61BD6D', '#1ABC9C', '#54ACD2', '#2C82C9', '#9365B8', '#475577', '#CCCCCC',
                                                '#41A85F', '#00A885', '#3D8EB9', '#2969B0', '#553982', '#28324E', '#000000',
                                                '#F7DA64', '#FBA026', '#EB6B56', '#E25041', '#A38F84', '#EFEFEF', '#FFFFFF',
                                                '#FAC51C', '#F37934', '#D14841', '#B8312F', '#7C706B', '#D1D5D8', 'REMOVE'
                                            ],
                                            colorsButtons: ["colorsBack", "|", "-"],
                                            colorsText: ['#61BD6D', '#1ABC9C', '#54ACD2', '#2C82C9', '#9365B8', '#475577', '#CCCCCC',
                                                '#41A85F', '#00A885', '#3D8EB9', '#2969B0', '#553982', '#28324E', '#000000',
                                                '#F7DA64', '#FBA026', '#EB6B56', '#E25041', '#A38F84', '#EFEFEF', '#FFFFFF',
                                                '#FAC51C', '#F37934', '#D14841', '#B8312F', '#7C706B', '#D1D5D8', 'REMOVE'
                                            ],
                                            imageEditButtons: ['imageReplace', 'imageAlign', 'imageRemove', '|', 'imageLink', 'linkOpen', 'linkEdit', 'linkRemove', '-', 'imageDisplay', 'imageStyle', 'imageAlt', 'imageSize'],
                                            //Images
                                            imageUpload: true,
                                            events: {
                                                // 'keypress': handleContentChanged,
                                                'image.beforeUpload': function (images) {
                                                    // Before image is uploaded
                                                    setButtonDisabled(true);

                                                    let imageData = new FormData();
                                                    imageData.append('file', images[0]);
                                                    imageData.append('id', document_template_id);
                                                    
                                                    axiosFetch({
                                                        url: route('froala.image-upload'), 
                                                        method: "post",
                                                        data: imageData
                                                    }).then(res => {
                                                        if(res.data.error){
                                                            AlertBox(
                                                                {
                                                                    title: "Error",
                                                                    text: res.data.error,
                                                                    confirmButtonColor: "#f1556c",
                                                                    confirmButtonText: "Ok",
                                                                    icon:'error',
                                                                    iconColor:'#f1556c'
                                                                }
                                                            );
                                                            setButtonDisabled(false);
                                                        }else{
                                                            this.image.insert(res.data.link, null, null, this.image.get());
                                                        }
                                                    });
                                                    return false;
                                                },
                                                'image.inserted': function () {
                                                    // Do something here.
                                                    // this is the editor instance.
                                                    setButtonDisabled(false);
                                                },
                                                'image.error': function(error,response){
                                                    setButtonDisabled(false);
                                                    console.log(error,response);
                                                }
                                            }
                                        }}
                                        model={data.body}
                                        onModelChange={(value) => {!loading && handleEditorChange(value)}}
                                        tag="textarea"
                                    />
                                </div>

                                <div className="form-group mt-2 d-flex flex-row-reverse">
                                    <button
                                        className="btn btn-secondary ms-1"
                                        type="submit"
                                        disabled={processing || buttonDisabled}
                                        onClick={() => setMode('draft')}
                                    >
                                        Save as Draft
                                    </button>
                                    <button
                                        className="btn btn-primary"
                                        type="submit"
                                        disabled={processing || buttonDisabled}
                                        onClick={() => setMode('publish')}
                                    >
                                        Publish
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
};

export default Show;