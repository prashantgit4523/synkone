import React, { useState } from "react";
import CampaignPolicyAcknowledgement from "../CampaignPolicyAcknowledgement";
import { Inertia } from "@inertiajs/inertia";
import Tab from "react-bootstrap/Tab";
import "./show-page.scss";
import { Player } from 'video-react';
import 'video-react/dist/video-react.css';
import { usePage } from "@inertiajs/inertia-react";
import {  useSelector } from "react-redux";

const ShowAwarenessPage = (props) => {
  const { APP_URL,link_path } = usePage().props;
  const link = APP_URL + link_path
  const thumbnail = APP_URL + 'awareness_video/thumbnail.png';
  const {
    campaignAcknowledgmentUserToken: {
      user,
      campaign,
      token: acknowledgmentUserToken,
    },
    campaignAcknowledgments,
    errors,
  } = props;
  const activeTab = campaignAcknowledgments[0]?.id;
  const [checkedPolicies, setCheckedPolicies] = useState([]);

  const newCampaignAcknowledgments = campaignAcknowledgments;

  const appDataScope = useSelector(
    (state) => state.appDataScope.selectedDataScope.value
);

  const handlePolicyChecked = (value) => {
    if(checkedPolicies.includes(value)){
      checkedPolicies.pop(value);
    }
    else{
      checkedPolicies.push(value);
    }
    setCheckedPolicies(checkedPolicies);
    console.log(checkedPolicies);
  };

  const enableSubmitButton= ()=>{
      document.getElementsByClassName('custom-save-button')[0].classList.remove('expandRight');
      document.getElementsByClassName('custom-save-button')[0].disabled = false
      document.getElementsByClassName('custom-spinner-image')[0].style.display = 'none';
  }

  const disableSubmitButton= ()=>{
    document.getElementsByClassName('custom-save-button')[0].classList.add('expandRight');
    document.getElementsByClassName('custom-save-button')[0].disabled = true
    document.getElementsByClassName('custom-spinner-image')[0].style.display = 'block';
  }

  /* Handling the form submit */
  const handleSubmit = (event) => {
    event.preventDefault();
    /* Starting loading button */
    disableSubmitButton();
    const formData = new FormData();

    /**/
    Object.keys(checkedPolicies).forEach((key) =>
      formData.append("agreed_policy[]", checkedPolicies[key])
    );
    formData.append(
      "campaign_acknowledgment_user_token",
      acknowledgmentUserToken
    );

    /* */
    Inertia.post(
      route("policy-management.campaigns.acknowledgement.confirm"),
      formData,
      {
        onSuccess: () => {
          /* Starting loading button */
          enableSubmitButton()

        },
        onError: () => {
          /* Starting loading button */
          enableSubmitButton()
        },
      }
    );
  };

  const renderTabContents = () => {
    return newCampaignAcknowledgments.map((campaignAcknowledgment, index) => {
      return (
        <Tab.Pane key={_.uniqueId()} eventKey={campaignAcknowledgment.id}>
          <div className="card-text">
            <div className="col-12 mt-3 text-center">
              <div className="form-check d-flex justify-content-center">
                <input
                  type="checkbox"
                  name="agreed_policy[]"
                  // defaultValue={checkedPolicies.includes(
                  //     campaignAcknowledgment.token
                  //   )?1:0}
                  className="form-check-input me-1 cursor-pointer"
                  id={`checkmeout_${index}`}
                  onChange={() => {
                    handlePolicyChecked(campaignAcknowledgment.token);
                  }}
                  defaultChecked={checkedPolicies.includes(
                    campaignAcknowledgment.token
                  )}
                />
                <label
                  className="form-check-label"
                  htmlFor={`checkmeout_${index}`}
                >
                  I have watched and understood the training material.
                </label>
              </div>

                {errors.agreed_policy && (
                  <div className="invalid-feedback d-block">{errors.agreed_policy}</div>
                )}
            </div>
            <div className="row mt-5 " id="button_div">
              <div className="col-12 text-center clearfix">
                  <button className="ms-1 btn btn-primary custom-save-button"onClick={(e) => handleSubmit(e)}>
                    Submit
                    <span className='custom-save-spinner'>
                      <img className='custom-spinner-image' style={{display: 'none'}} height="25px"></img>
                    </span>
                  </button>
              </div>
            </div>
          </div>
        </Tab.Pane>
      );
    });
  };

  return (
    <CampaignPolicyAcknowledgement>
      <div className="row" id="campaign-policy-acknowledgement-show-page">
        <div className="col-12 m-30 title-heading text-center">
          <h5 className="card-title">
            Hi {decodeHTMLEntity(user.first_name)}&nbsp;
            {decodeHTMLEntity(user.last_name)},
          </h5>
          <p>
            You have been enrolled in the{" "}
            <strong>{decodeHTMLEntity(campaign.name)}</strong>, a company wide security awareness campaign. Please watch the training material below and acknowledge it.
          </p>
          <Player
            playsInline
            poster={thumbnail}
            src={link}
          />
        </div>
        <Tab.Container id="left-tabs-example" activeKey={activeTab} mountOnEnter>
          <div className="col-12 col-sm-12">
              <input
                type="hidden"
                name="campaign_acknowledgment_user_token"
                defaultValue={acknowledgmentUserToken}
              />
              <Tab.Content>{renderTabContents()}</Tab.Content>
            {/* </form> */}
          </div>
        </Tab.Container>
      </div>
    </CampaignPolicyAcknowledgement>
  );
};

export default ShowAwarenessPage;
