import React, { useEffect, useState } from "react";
import Select from "../../../common/custom-react-select/CustomReactSelect";
import MicrosoftIcon from "../../../../../public/assets/images/sso-icons/office-365.png";
import GoogleIcon from "../../../../../public/assets/images/sso-icons/google.png";
import '../styles/style.css';
import Swal from "sweetalert2";
import {Inertia} from "@inertiajs/inertia";
import LoadingButton from '../../../common/loading-button/LoadingButton';
import {showToastMessage} from "../../../utils/toast-message";

import { Link, useForm, usePage } from "@inertiajs/inertia-react";
import {Accordion, Alert} from "react-bootstrap";

const SMTPSettingsTab = ({mailSettings, smtpProviders, connectedSmtpProvider, aliases}) => {
    const {
        form_actions: {mail_settings: mail_form_action},
        connection_test_routes: {mail_settings: connection_test_route},
        is_mail_testable
    } = usePage().props;

    const {data, setData, processing, post, errors} = useForm({
        mail_host: mailSettings?.mail_host ?? '',
        mail_port: mailSettings?.mail_port ?? '',
        mail_encryption: mailSettings?.mail_encryption ?? '',
        mail_username: mailSettings?.mail_username ?? '',
        mail_password: mailSettings?.mail_password ?? '',
        mail_from_address: mailSettings?.mail_from_address ?? '',
        mail_from_name: mailSettings?.mail_from_name ?? ''
    });

    const [oauthErrors, setOauthErrors] = useState({});
    const [oauthData, setOauthData] = useState({from_address: connectedSmtpProvider?.from_address ?? ''});
    const [updatingOauthForm, setUpdatingOauthForm] = useState(false);
    const [fetchingAliases, setFetchingAliases] = useState(false);
    const [aliasesData, setAliasesData] = useState(aliases);
    const [selectedAlias, setSelectedAlias] = useState(() => {
        if(connectedSmtpProvider && connectedSmtpProvider.slug === 'gmail'){
            let selected = aliasesData.filter((alias) => {
                if(alias.selected){
                    return alias
                }
            });
            return {
                value: selected[0].email, 
                label: selected[0].name + " <"+ selected[0].email +"> (Status: "+selected[0].verificationStatus+")"
            };
        }
        return {};
    });

    useEffect(() => {
        if(connectedSmtpProvider && connectedSmtpProvider.slug === 'gmail'){
            let selected = aliasesData.filter((alias) => {
                if(alias.selected){
                    return alias
                }
            });
            
            setSelectedAlias({
                value: selected[0].email, 
                label: selected[0].name + " <"+ selected[0].email +"> (Status: "+selected[0].verificationStatus+")"
            });
            setOauthData({from_address: selected[0].email});
        }
    },[aliasesData]);

    const handleSubmit = (e) => {
        e.preventDefault();
        
        if(connectedSmtpProvider){
            Swal.fire({
                title: "Are you sure?",
                text: `This will remove already setup "${connectedSmtpProvider.provider_name}" SMTP setting.`,
                showCancelButton: true,
                confirmButtonColor: "#f1556c",
                confirmButtonText: "Yes",
                icon: 'warning',
                iconColor: '#f1556c',
            }).then((confirmed) => {
                if (confirmed.value) {
                    post(mail_form_action);
                }
            });
        }else{
            post(mail_form_action);
        }
    };

    const refreshAliases = (e) => {
        e.preventDefault();

        setFetchingAliases(true);

        axiosFetch.get("/smtp/auth/refreshAlias")
            .then(response => {
                if(response.data){
                    setOauthErrors({});
                    setFetchingAliases(false);
                    showToastMessage(response.data.message, response.data.status)
                    if(response.data.status === 'error'){
                        setOauthErrors({from_address: response.data.message.from_address});
                    }

                    if(response.data.data){
                        setAliasesData(response.data.data);
                    }
                }
            });
    }

    const handleOauthSubmit = (e) => {
        e.preventDefault();
        setUpdatingOauthForm(true);

        axiosFetch.post("/smtp/auth/update",oauthData)
            .then(response => {
                if(response.data){
                    setOauthErrors({});
                    setUpdatingOauthForm(false);
                    showToastMessage(response.data.message, response.data.status)
                    if(response.data.status === 'error'){
                        setOauthErrors({from_address: response.data.message.from_address});
                    }
                }
            });
    }

    const connectSmtpProvider = (e, url) => {
        e.preventDefault();

        if(is_mail_testable){
            Swal.fire({
                title: "Are you sure?",
                text: 'This will remove already setup manual SMTP setting.',
                showCancelButton: true,
                confirmButtonColor: "#f1556c",
                confirmButtonText: "Yes",
                icon: 'warning',
                iconColor: '#f1556c',
            }).then((confirmed) => {
                if (confirmed.value) {
                    window.location.href = url;
                }
            });
        }else{
            window.location.href = url;
        }
    };

    const disconnectProvider = (e, provider) => {
        e.preventDefault();

        Swal.fire({
            title: "Are you sure?",
            text: 'This will remove your SMTP settings.',
            showCancelButton: true,
            confirmButtonColor: "#f1556c",
            confirmButtonText: "Yes",
            icon: 'warning',
            iconColor: '#f1556c',
        }).then((confirmed) => {
            if (confirmed.value) {
                Inertia.post(route('smtp-provider.disconnect'), {
                    slug: provider
                }, {
                    preserveState: false,
                    onSuccess: page => {
                        console.log('page');
                    },
                });
            }
        });
    }

    return (
        <div className={"global"}>
            {mailSettings && mailSettings.mail_host === 'smtp.office365.com' && <Alert variant={"warning d-flex align-items-center justify-content-between mb-3"}>
                <span>
                    <i className="fas fa-exclamation-triangle flex-shrink-0 me-1"/>
                    <span>Please update your mail settings by connecting to <b>Office 365</b>, as basic connect (using username and password)
                        will no longer be supported by Microsoft and email will no longer work beginning with 1'st of October.</span>
                </span>
            </Alert>}

            <div className="row mb-4">
                <h5 className="page-title" style={{marginLeft: '5px', marginBottom: '25px'}}>Use the button below to automatically set up your email settings.</h5>
            
                {smtpProviders.map((provider, index) => {
                    return (
                        <div className="col-6" key={index}>
                            <div className="card bg-pattern">
                                <div className="card-body text-center">
                                    <div>
                                        <img src={provider.slug === 'gmail'  ? GoogleIcon : MicrosoftIcon} alt={provider.provider_name} className="avatar-md mb-1"/>
                                        <h4 className="mb-3 font-20 clamp clamp-1">{ provider.slug === 'gmail' ? 'Gmail / G-suite' :provider.provider_name }</h4>
                                    </div>

                                    <div className="text-center">
                                        {connectedSmtpProvider && connectedSmtpProvider.slug !== provider.slug ? (<button className="btn disabled btn-sm width-sm">
                                            Connect
                                        </button>) : (<button className="btn btn-primary btn-sm width-sm" onClick={(e) => provider.connected ? disconnectProvider(e, provider.slug) : connectSmtpProvider(e, `/smtp/auth/${provider.slug}/redirect`)}>
                                            {provider.connected ? 'Disconnect' : 'Connect'}
                                        </button>)}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                )}

                    {connectedSmtpProvider && <div className="row mt-5 oauth-smtp-form">
                            <div className="row mb-3 sender-address">
                                <label
                                    htmlFor="from_address"
                                    className="col-xl-3 col-lg-3 col-md-3 col-sm-3 form-label mt-1"
                                >
                                    Sender Address <span className="text-danger">*</span>
                                </label>
                                <div className="col-xl-9 col-lg-9 col-md-9 col-sm-9">
                                {connectedSmtpProvider.slug === 'office-365' ? <>
                                    <input
                                        type="email"
                                        className="form-control"
                                        name="from_address"
                                        onChange={(e) => {
                                            setOauthData({from_address: e.target.value})
                                        }}
                                        defaultValue={oauthData.from_address}
                                    />
                                    {oauthErrors.from_address && (
                                        <div className="invalid-feedback d-block">
                                            {oauthErrors.from_address}
                                        </div>
                                    )}
                                    </> : 
                                    <>
                                        <Select
                                        className="react-select"
                                        classNamePrefix="react-select"
                                        defaultValue={selectedAlias}
                                        onChange={val => setOauthData({from_address: val.value})}
                                        options={aliasesData.map((alias) => ({
                                            value: alias.email,
                                            label: alias.name + " <"+ alias.email +"> (Status: "+alias.verificationStatus+")",
                                            isDisabled: alias.verificationStatus === 'pending'
                                        }))}
                                        />
                                    </>
                                }
                                </div>
                            </div> 
                            
                            <div className="row">
                                <div className="col-xl-3 col-lg-3 col-md-3 col-sm-3"/>
                                <div className="save-button col-xl-9 col-lg-9 col-md-9 col-sm-9 d-flex justify-content-center my-3">
                                        {connectedSmtpProvider.slug === 'gmail' && <LoadingButton onClick={refreshAliases} loading={fetchingAliases} disabled={fetchingAliases} className="btn btn-primary width-lg secondary-bg-color">Refresh Aliases</LoadingButton>}
                                        <LoadingButton
                                            onClick={handleOauthSubmit}
                                            className="btn btn-primary width-lg secondary-bg-color ms-3"
                                            loading={updatingOauthForm}
                                            disabled={updatingOauthForm}
                                        >
                                            Test & Save
                                        </LoadingButton>
                                </div>
                            </div>
                     </div>}
            </div>

            <Accordion>
                <Accordion.Item key="1" eventKey="1">
                    <Accordion.Header as="div">
                        <div className="d-flex w-100 justify-content-between">
                            <span className="d-inline-flex align-items-center fw-bold">
                                <span style={{marginLeft: '5px'}}>Manual SMTP</span>
                            </span>
                        </div>
                    </Accordion.Header>
                    <Accordion.Body>
                        <form onSubmit={handleSubmit} method="post">
                            {connectedSmtpProvider && <Alert variant={"warning d-flex align-items-center justify-content-between mt-2 mb-4"}>
                                <span>
                                    <i className="fas fa-exclamation-triangle flex-shrink-0 me-1"/>
                                    <span>Setting up SMTP manually will remove the setup done with <b>{connectedSmtpProvider.provider_name}.</b></span>
                                </span>
                            </Alert>}
                            <div className="row mb-3">
                                <label htmlFor="mail_host" className="col-xl-3 col-lg-3 col-md-3 col-sm-3 form-label">SMTP Host <span
                                    className="text-danger">*</span></label>
                                <div className="col-xl-9 col-lg-9 col-md-9 col-sm-9">
                                    <input type="text" name="mail_host" id="mail_host" className="form-control"
                                        value={data.mail_host} onChange={e => setData('mail_host', e.target.value)}/>
                                    {errors.mail_host && (
                                        <div className="invalid-feedback d-block">
                                            {errors.mail_host}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="row mb-3">
                                <label htmlFor="mail_port" className="col-xl-3 col-lg-3 col-md-3 col-sm-3 form-label">SMTP Port <span
                                    className="text-danger">*</span></label>
                                <div className="col-xl-9 col-lg-9 col-md-9 col-sm-9">
                                    <input type="text" id="mail_port" name="mail_port" className="form-control"
                                        value={data.mail_port} onChange={e => setData('mail_port', e.target.value)}/>
                                    {errors.mail_port && (
                                        <div className="invalid-feedback d-block">
                                            {errors.mail_port}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="row mb-3">
                                <label htmlFor="color" className="col-xl-3 col-lg-3 col-md-3 col-sm-3 form-label">SMTP Security <span
                                    className="text-danger">*</span></label>
                                <div className="col-xl-9 col-lg-9 col-md-9 col-sm-9">
                                    <Select
                                        className="react-select"
                                        classNamePrefix="react-select"
                                        defaultValue={{ label: data.mail_encryption?.toUpperCase(), value: data?.mail_encryption?.toLowerCase() }}
                                        onChange={val => setData('mail_encryption', val.value)}
                                        options={[
                                            { label: 'TLS', value: 'tls' },
                                            { label: 'SSL', value: 'ssl' }
                                        ]}
                                    />
                                    {errors.mail_encryption && (
                                        <div className="invalid-feedback d-block">
                                            {errors.mail_encryption}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="row mb-3">
                                <label htmlFor="mail_username" className="col-xl-3 col-lg-3 col-md-3 col-sm-3 form-label">SMTP Username <span
                                    className="text-danger">*</span></label>
                                <div className="col-xl-9 col-lg-9 col-md-9 col-sm-9">
                                    <input type="text" id="mail_username" className="form-control" name="mail_username"
                                        value={data.mail_username} onChange={e => setData('mail_username', e.target.value)}/>
                                    {errors.mail_username && (
                                        <div className="invalid-feedback d-block">
                                            {errors.mail_username}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="row mb-3">
                                <label
                                    htmlFor="mail_password"
                                    className="col-xl-3 col-lg-3 col-md-3 col-sm-3 form-label"
                                >
                                    SMTP Password <span className="text-danger">*</span>
                                </label>
                                <div className="col-xl-9 col-lg-9 col-md-9 col-sm-9">
                                    <input
                                        type="password"
                                        id="mail_password"
                                        className="form-control"
                                        name="mail_password"
                                        value={data.mail_password}
                                        onChange={(e) =>
                                            setData("mail_password", e.target.value)
                                        }
                                    />
                                    {errors.mail_password && (
                                        <div className="invalid-feedback d-block">
                                            {errors.mail_password}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="row mb-3">
                                <label
                                    htmlFor="mail_from_address"
                                    className="col-xl-3 col-lg-3 col-md-3 col-sm-3 form-label"
                                >
                                    From Address <span className="text-danger">*</span>
                                </label>
                                <div className="col-xl-9 col-lg-9 col-md-9 col-sm-9">
                                    <input
                                        type="text"
                                        id="mail_from_address"
                                        className="form-control"
                                        name="mail_from_address"
                                        value={data.mail_from_address}
                                        onChange={(e) =>
                                            setData("mail_from_address", e.target.value)
                                        }
                                    />
                                    {errors.mail_from_address && (
                                        <div className="invalid-feedback d-block">
                                            {errors.mail_from_address}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="row mb-3">
                                <label
                                    htmlFor="mail_from_name"
                                    className="col-xl-3 col-lg-3 col-md-3 col-sm-3 form-label"
                                >
                                    From Name <span className="text-danger">*</span>
                                </label>
                                <div className="col-xl-9 col-lg-9 col-md-9 col-sm-9">
                                    <input
                                        type="text"
                                        id="mail_from_name"
                                        className="form-control"
                                        name="mail_from_name"
                                        value={data.mail_from_name}
                                        onChange={(e) =>
                                            setData("mail_from_name", e.target.value)
                                        }
                                    />
                                    {errors.mail_from_name && (
                                        <div className="invalid-feedback d-block">
                                            {errors.mail_from_name}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="save-button d-flex justify-content-end my-3">
                                {is_mail_testable ? (
                                    <Link href={connection_test_route} className="btn btn-primary width-lg secondary-bg-color">Test
                                        Connection</Link>
                                ) : null}
                                <button type="submit" className="btn btn-primary width-lg secondary-bg-color ms-3"
                                        disabled={processing}>{processing ? 'Saving' : 'Save'}</button>
                            </div>
                        </form>
                    </Accordion.Body>
                </Accordion.Item>
            </Accordion>
        </div>
    );
};

export default SMTPSettingsTab;
