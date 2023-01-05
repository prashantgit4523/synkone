import React, { useEffect, useState } from "react";

import { useForm, usePage } from "@inertiajs/inertia-react";
import Switch from "rc-switch";
import Select from "../../../common/custom-react-select/CustomReactSelect";

import CustomColorPicker from "../../../common/custom-color-picker/CustomColorPicker";
import CustomDropify from "../../../common/custom-dropify/CustomDropify";

import "rc-switch/assets/index.css";

const GlobalSettingsTab = ({
    globalSetting,
    timezones,
    sessionExpiryTimes,
}) => {
    const {
        form_actions: { global_settings },
        APP_URL,
        file_driver
    } = usePage().props;
    const selectableTimezones = Object.entries(timezones).map(
        ([index, timezone]) => ({ value: index, label: timezone })
    );
    const selectableSessionTimeouts = Object.entries(sessionExpiryTimes).map(
        ([index, expiryTime]) => ({ value: index, label: expiryTime })
    );

    const { data, setData, errors, post, processing, reset,getValues } = useForm({
        display_name: globalSetting.display_name,
        primary_color: globalSetting.primary_color,
        secondary_color: globalSetting.secondary_color,
        default_text_color: globalSetting.default_text_color,
        timezone: globalSetting.timezone,
        company_logo: null,
        favicon: null,
        allow_document_upload: globalSetting.allow_document_upload,
        allow_document_link: globalSetting.allow_document_link,
        session_timeout: globalSetting.session_timeout ?? "null",
        secure_mfa_login: globalSetting.secure_mfa_login,
    });

    const handleOnCompanyLogoSelected = (file) =>
        setData((previousData) => ({
            ...previousData,
            company_logo: file,
        }));
    const handleOnFaviconSelected = (file) =>
        setData((previousData) => ({
            ...previousData,
            favicon: file,
        }));

    const ColorLuminance=(hex,lum) =>{
        // validate hex string
        hex = String(hex).replace(/[^0-9a-f]/gi, '');
        if (hex.length < 6) {
            hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
        }
        lum = lum || 0;

        // convert to decimal and change luminosity
        var rgb = "#", c, i;
        for (i = 0; i < 3; i++) {
            c = parseInt(hex.substr(i*2,2), 16);
            c = Math.round(Math.min(Math.max(0, c + (c * lum)), 255)).toString(16);
            rgb += ("00"+c).substr(c.length);
        }

        return rgb;
    }

    const handleSubmit = (e) => {
        e.preventDefault();
        console.log(data['display_name'].length)
        post(global_settings, {
            onSuccess: () => {
                // update css variables
                document.documentElement.style.setProperty('--primary-color', data.primary_color);
                document.documentElement.style.setProperty('--secondary-color', data.secondary_color);
                document.documentElement.style.setProperty('--default-text-color', data.default_text_color);

                var primary_bg_color_hover = ColorLuminance(data.primary_color, -0.1)
                var secondary_bg_color_hover = ColorLuminance(data.secondary_color, -0.1)
                var secondary_color_darker = ColorLuminance(data.secondary_color, -0.2)

                document.documentElement.style.setProperty('--primary-color-hover', primary_bg_color_hover);
                document.documentElement.style.setProperty('--secondary-color-hover', secondary_bg_color_hover);
                document.documentElement.style.setProperty('--secondary-color-darker', secondary_color_darker);

                reset({
                    ...getValues(),
                    display_name: data.display_name
                });
            },
        });
    };

    function faviconLogo(boolean) {
        if(globalSetting.favicon ==="assets/images/cyberarrow-favicon.png")
            return "assets/images/cyberarrow-favicon.png"
        else if(globalSetting.favicon ==="assets/images/ebdaa-Logo.png")
            return "assets/images/ebdaa-Logo.png"
        else
            return boolean ? asset(globalSetting.favicon) : globalSetting.favicon
    }
   
    function handleOnChange(e) {
        //if has a error then remove it
        if(errors[e.target.name]){
            if (e.target.value.length > 0) {
                errors[e.target.name] = null;
            }
        }
    }
    return (
        <div className={"global"}>
            <form
                onSubmit={handleSubmit}
                method="post"
                encType="multipart/form-data"
            >
                {/*<input type="hidden" name="global_settings" value="1" />*/}
                <div className="row mb-3">
                    <label
                        htmlFor="displayname"
                        className="col-xl-3 col-lg-3 col-md-3 col-sm-3 form-label"
                    >
                        Display Name{" "}
                        <span className="required text-danger">*</span>
                    </label>
                    <div className="col-xl-9 col-lg-9 col-md-9 col-sm-9">
                        <input
                            type="text"
                            name="display_name"
                            className="form-control"
                            value={data.display_name}
                            maxLength={153}
                            onChange={(e) => {
                                e.target.value.toString().length>153 ?
                                e.preventDefault()
                                :
                                setData("display_name", e.target.value);
                                handleOnChange(e);
                    
                            }}
                        />
                        {errors.display_name && (
                            <div className="invalid-feedback d-block">
                                {errors.display_name}
                            </div>
                        )}
                    </div>
                </div>

                <div className="row mb-3">
                    <label
                        htmlFor="color"
                        className="col-xl-3 col-lg-3 col-md-3 col-sm-3"
                    >
                        Primary Color{" "}
                        <span className="required text-danger">*</span>
                    </label>
                    <div className="col-xl-9 col-lg-9 col-md-9 col-sm-9">
                        <CustomColorPicker
                            color={data.primary_color}
                            onChange={(color) =>
                                setData("primary_color", color)
                            }
                        />
                        {errors.primary_color && (
                            <div className="invalid-feedback d-block">
                                {errors.primary_color}
                            </div>
                        )}
                    </div>
                </div>

                <div className="row mb-3">
                    <label
                        htmlFor="color"
                        className="col-xl-3 col-lg-3 col-md-3 col-sm-3"
                    >
                        Secondary Color{" "}
                        <span className="required text-danger">*</span>
                    </label>
                    <div className="col-xl-9 col-lg-9 col-md-9 col-sm-9">
                        <CustomColorPicker
                            color={data.secondary_color}
                            onChange={(color) =>
                                setData("secondary_color", color)
                            }
                        />
                        {errors.secondary_color && (
                            <div className="invalid-feedback d-block">
                                {errors.secondary_color}
                            </div>
                        )}
                    </div>
                </div>

                <div className="row mb-3">
                    <label
                        htmlFor="color"
                        className="col-xl-3 col-lg-3 col-md-3 col-sm-3"
                    >
                        Default Text Color{" "}
                        <span className="required text-danger">*</span>
                    </label>
                    <div className="col-xl-9 col-lg-9 col-md-9 col-sm-9">
                        <CustomColorPicker
                            color={data.default_text_color}
                            onChange={(color) =>
                                setData("default_text_color", color)
                            }
                        />
                        {errors.default_text_color && (
                            <div className="invalid-feedback d-block">
                                {errors.default_text_color}
                            </div>
                        )}
                    </div>
                </div>

                <div className="row my-2">
                    <div className="col-xl-3">
                        <label>Time Zone</label>
                    </div>
                    <div className="col-xl-9">
                        <Select
                            className="react-select"
                            classNamePrefix="react-select"
                            defaultValue={selectableTimezones.filter(
                                (timezone) => timezone.value === data.timezone
                            )}
                            onChange={(option) =>
                                setData("timezone", option.value)
                            }
                            options={selectableTimezones}
                        />
                        {errors.timezone && (
                            <div className="invalid-feedback d-block">
                                {errors.timezone}
                            </div>
                        )}
                    </div>
                </div>

                <div className="row my-2">
                    <div className="col-xl-3 col-lg-3 col-md-3">
                        <label>Company Logo</label>
                    </div>
                    <div className="col-xl-9 col-lg-9 col-md-9">
                        <p className="sub-header">
                            The recommended image size for Company Logo is 300
                            by 300 pixels.
                        </p>
                        {file_driver =="s3"?
                            <CustomDropify
                                onSelect={handleOnCompanyLogoSelected}
                                file={data.company_logo}
                                defaultPreview={globalSetting.company_logo==="assets/images/ebdaa-Logo.png"? "assets/images/ebdaa-Logo.png": globalSetting.company_logo }
                                accept={'image/*'}
                            />
                            :
                            <CustomDropify
                                onSelect={handleOnCompanyLogoSelected}
                                file={data.company_logo}
                                defaultPreview={globalSetting.company_logo==="assets/images/ebdaa-Logo.png"? "assets/images/ebdaa-Logo.png": asset(globalSetting.company_logo) }
                                accept={'image/*'}
                            />
                        }
                        {errors.company_logo && (
                            <div className="invalid-feedback d-block">
                                {errors.company_logo}
                            </div>
                        )}
                    </div>
                </div>

                <div className="row my-2">
                    <div className="col-xl-3 col-lg-3 col-md-3">
                        <label>Favicon</label>
                    </div>
                    <div className="col-xl-9 col-lg-9 col-md-9">
                        <p className="sub-header">
                            The recommended image size for Favicon is 64 by 64
                            pixels.
                        </p>
                        {file_driver =="s3"?
                            <CustomDropify
                                onSelect={handleOnFaviconSelected}
                                file={data.favicon}
                                defaultPreview={faviconLogo(false)}
                                accept={'image/*'}
                            />
                            :
                            <CustomDropify
                                onSelect={handleOnFaviconSelected}
                                file={data.favicon}
                                defaultPreview={faviconLogo(true)}
                                accept={'image/*'}
                            />

                        }
                        {errors.favicon && (
                            <div className="invalid-feedback d-block">
                                {errors.favicon}
                            </div>
                        )}
                    </div>
                </div>

                <div className="row my-2">
                    <div className="col-lg-3 col-md-5 col-sm-6 col-6">
                        <p>Document Upload Allowed</p>
                    </div>
                    <div className="col-lg-9 col-md-7 col-sm-6 col-6">
                        <Switch
                            checked={data.allow_document_upload}
                            onChange={(checked) =>
                                setData("allow_document_upload", checked)
                            }
                        />
                    </div>
                </div>

                <div className="row">
                    <div className="col-lg-3 col-md-5 col-sm-6 col-6">
                        <p>Links Upload Allowed</p>
                    </div>
                    <div className="col-lg-9 col-md-7 col-sm-6 col-6">
                        <Switch
                            checked={data.allow_document_link}
                            onChange={(checked) =>
                                setData("allow_document_link", checked)
                            }
                        />
                    </div>
                </div>

                <div className="row my-2">
                    <div className="col-xl-3">
                        <label className="form-label">Session Timeout</label>
                    </div>
                    <div className="col-xl-9 session-timeout">
                        <Select
                            className="react-select"
                            classNamePrefix="react-select"
                            defaultValue={selectableSessionTimeouts.filter((session) => session.value == data.session_timeout)}
                            onChange={val => setData('session_timeout', val.value)}
                            options={selectableSessionTimeouts}
                        />
                        {errors.session_timeout && (
                            <div className="invalid-feedback d-block">
                                {errors.session_timeout}
                            </div>
                        )}
                    </div>
                </div>

                <div className="row">
                    <div className="col-xl-3">
                        <label className="form-label">Secure MFA Login</label>
                    </div>
                    <div className="col-xl-9 secure-mfa-login">
                        <Select
                            className="react-select"
                            classNamePrefix="react-select"
                            defaultValue={globalSetting.secure_mfa_login == 1 ? 
                                { label: 'Mandatory', value: '1' }:
                                { label: 'Optional', value: '0' }
                            }
                            onChange={val => setData('secure_mfa_login', val.value)}
                            options={[
                                { label: 'Optional', value: '0' },
                                { label: 'Mandatory', value: '1' }
                            ]}
                        />
                        {errors.secure_mfa_login && (
                            <div className="invalid-feedback d-block">
                                {errors.secure_mfa_login}
                            </div>
                        )}
                    </div>
                </div>

                <div className="save-button d-flex justify-content-end my-3">
                    <button
                        type="submit"
                        id="global-setting-save-btn"
                        className="btn btn-primary width-lg secondary-bg-color"
                        disabled={processing}
                    >
                        {processing ? "Saving" : "Save"}
                    </button>
                </div>
            </form>
        </div>
    );
};

export default GlobalSettingsTab;
