import React from "react";
import CampaignPolicyAcknowledgement from "../CampaignPolicyAcknowledgement";
import { Link } from "@inertiajs/inertia-react";


const CompletedPage = (props) => {
    const { campaignAcknowledgments, user, acknowledgement_completed, previous_url } = props;

    const renderACknowledgedPoliciesSection = () => {
        return campaignAcknowledgments.map((campaignAcknowledgment, index) => {
            return (
                <h5 className="text-center">
                    #{index + 1}
                    {decodeHTMLEntity(
                        campaignAcknowledgment.policy.display_name
                    )}
                </h5>
            );
        });
    };

    return (
        <CampaignPolicyAcknowledgement>
            <div className="row">
                <div className="col-12 text-center">
                    {user && <h5 className="card-title">
                        Hi {`${decodeHTMLEntity(user.first_name)}
                        ${decodeHTMLEntity(user.last_name)}`}
                        ,
                    </h5>}
                    {campaignAcknowledgments && <div className="card-text">
                        {campaignAcknowledgments[0].policy.type === 'awareness' &&
                        <>
                            <p className="text-center h4 mb-3">
                                Thank you for completing the awareness training.
                            </p>
                            <p className="text-center h4 mb-3">
                                You can now close this window. 
                            </p>
                        </>
                        }
                        {campaignAcknowledgments[0].policy.type != 'awareness' &&
                        <>
                            <p className="text-center h4 mb-3">
                                Thank you for acknowledging the following
                                policy(ies):
                            </p>
                            {renderACknowledgedPoliciesSection()}
                            <br/> <br/>
                            {acknowledgement_completed ? 
                                <p className="text-center h4 mb-3">
                                    You can now close this window.
                                </p>
                                :
                                <p className="text-center h4 mb-3">
                                   You still have pending policies to acknowledge. <Link href={previous_url} >Go Back</Link> to acknowledge the remaining policies, alternatively revisit the campaign link from your mailbox.
                                </p>
                            }

                            </>
                        }
                    </div>}
                </div>
            </div>
        </CampaignPolicyAcknowledgement>
    );
};

export default CompletedPage;
