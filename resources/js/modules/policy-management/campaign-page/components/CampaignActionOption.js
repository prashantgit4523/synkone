import React, { Fragment } from "react";
import Dropdown from "react-bootstrap/Dropdown";
import {
    deleteCampaigns,
    fetchCampaignList,
    completeCampaign,
} from "../../../../store/actions/policy-management/campaigns";
import { useDispatch } from "react-redux";
import { duplicateCampaigns } from "../../../../store/actions/policy-management/campaigns";

function CampaignActionOption(props) {
    const { campaign, searchQuery, campaignTypeFilter, appDataScope } = props;
    const dispatch = useDispatch();

    const handleCampaignComplete = () => {
        AlertBox(
            {
                title: "Are you sure?",
                text: "This campaign will be marked as complete!",
                showCancelButton: true,
                confirmButtonColor: "#ff0000",
                confirmButtonText: "Yes, Complete!",
                closeOnConfirm: false,
                icon: 'warning',
                iconColor: '#ff0000'
            },
            async function (confirmed) {
                if (confirmed.value) {
                    let { payload } = await dispatch(
                        completeCampaign({ campaignId: campaign.id, })
                    );

                    /* when deleted successfully */
                    if (payload.success) {
                        /* render campaigns */
                        AlertBox({
                            title: "Done!",
                            text: "The campaign was marked as complete",
                            confirmButtonColor: "#b2dd4c",
                            icon: 'success',
                        });
                        dispatch(
                            fetchCampaignList({
                                campaign_name: searchQuery,
                                campaign_status: campaignTypeFilter,
                                data_scope: appDataScope,
                            })
                        );
                    }
                }

            }
        );
    };

    const delete_message = campaign.campaign_type === 'awareness-campaign' ? 'Deleting the awareness course will remove implementation for awareness implemented controls aswell.' : 'You will not be able to recover this campaign!'

    const handleDelete = () => {
        AlertBox(
            {
                title: "Are you sure?",
                text: delete_message,
                showCancelButton: true,
                confirmButtonColor: "#ff0000",
                confirmButtonText: "Yes, delete it!",
                closeOnConfirm: false,
                icon: 'warning',
                iconColor: '#ff0000'
            },
            async function (confirmed) {
                if (confirmed.value && confirmed.value == true) {
                    dispatch({ type: "reportGenerateLoader/show", payload: "Deleting..." });
                    let { payload } = await dispatch(
                        deleteCampaigns(campaign.id)
                    );

                    /* when deleted successfully */
                    if (payload.success) {
                        /* render campaigns */
                        AlertBox({
                            title: "Deleted!",
                            text: "The campaign was deleted successfully",
                            confirmButtonColor: "#b2dd4c",
                            icon: 'success',
                        });
                        dispatch(
                            fetchCampaignList({
                                campaign_name: searchQuery,
                                campaign_status: campaignTypeFilter,
                                data_scope: appDataScope,
                            })
                        );
                    }
                    dispatch({ type: "reportGenerateLoader/hide" });
                }

            }
        );
    };

    const handleCampaignDuplicate = async () => {
        await dispatch(
            duplicateCampaigns({
                campaignId: campaign.id,
                params: {
                    data_scope: appDataScope,
                },
            })
        );
    };

    return (
        <Fragment>
            <Dropdown className="float-end cursor-pointer">
                <Dropdown.Toggle as="a">
                    <i className="mdi mdi-dots-horizontal m-0 text-muted h3" />
                </Dropdown.Toggle>

                <Dropdown.Menu className="dropdown-menu-end">
                    {
                        campaign.status === 'In progress' &&
                        <Dropdown.Item eventKey="2" onClick={handleCampaignComplete} className="d-flex align-items-center">
                            <i className="mdi mdi-flag-variant-outline font-18 me-1" /> Complete
                        </Dropdown.Item>
                    }
                    {
                        campaign.campaign_type != 'awareness-campaign' &&
                        <Dropdown.Item eventKey="1" onClick={handleCampaignDuplicate} className="d-flex align-items-center">
                            <i className="mdi mdi-content-copy font-14 me-1" /> Duplicate
                        </Dropdown.Item>
                    }
                    <Dropdown.Item eventKey="3" onClick={handleDelete} className="d-flex align-items-center">
                        <i className="mdi mdi-delete-outline font-18 me-1" /> Delete
                    </Dropdown.Item>
                </Dropdown.Menu>
            </Dropdown>
        </Fragment>
    );
}

export default CampaignActionOption;
