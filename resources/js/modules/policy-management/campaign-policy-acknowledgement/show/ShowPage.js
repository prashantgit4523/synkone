import React, { useState, useEffect } from "react";
import CampaignPolicyAcknowledgement from "../CampaignPolicyAcknowledgement";
import { Inertia } from "@inertiajs/inertia";
import { usePage } from "@inertiajs/inertia-react";
import Tab from "react-bootstrap/Tab";
import Nav from "react-bootstrap/Nav";
import "./show-page.scss";
import { Document, Page, pdfjs } from 'react-pdf';
import pdfjsWorker from "pdfjs-dist/build/pdf.worker.entry";
import { SizeMe } from 'react-sizeme';
import { Link } from "react-router-dom";
import ContentLoader from "../../../../common/content-loader/ContentLoader";
import ReactPagination from "../../../../common/react-pagination/ReactPagination";

pdfjs.GlobalWorkerOptions.workerSrc = pdfjsWorker;

const ShowPage = (props) => {
  const { file_driver } = usePage().props;
  const {
    campaignAcknowledgmentUserToken: {
      user,
      campaign,
      token: acknowledgmentUserToken,
    },
    campaignAcknowledgments,
    paginationData,
    first_render,
    errors,
  } = props;
  const [activeTab, setActiveTab] = useState(campaignAcknowledgments[0]?.id);
  
  const [checkedPolicies, setCheckedPolicies] = useState(localStorage.getItem('checked_policies')==undefined?[]:JSON.parse(localStorage.getItem('checked_policies')));
  const [numPages, setNumPages] = useState(null);
  const [pageNumber, setPageNumber] = useState(1);
  const [loading, setLoading] = useState(false);

  const [newCampaignAcknowledgments,setNewCampaignAcknowledgments]=useState(campaignAcknowledgments);
  const [currentPolicyPage,setCurrentPolicyPage] =useState(paginationData.current_page);

  function onDocumentLoadSuccess({ numPages }) {
    setNumPages(numPages);
  }

  useEffect(() => {
    if(first_render){
      localStorage.removeItem('checked_policies');
    }
  }, [first_render])

  const handleTabNavClick =async (tabId,index) => {
    toggleLoading(true);
    setPageNumber(1);
    if(file_driver =="s3"){
        await getNewPolicyFileUrl(acknowledgmentUserToken,index);
    }
    setActiveTab(tabId);
    toggleLoading(false);
  };

  const handleNextBtnClick =async (index, lastElement) => {
    toggleLoading(true);
    setPageNumber(1);
    if(lastElement){
      fetchPolicy(currentPolicyPage+1);
    }
    else{
      if(file_driver =="s3"){
        await getNewPolicyFileUrl(acknowledgmentUserToken,index);
      }
      setActiveTab(activeTab + 1);
    }
    toggleLoading(false);
  };

  const handlePrevBtnClick =async (index) => {
    toggleLoading(true);
    setPageNumber(1);
    if(index==0){
      fetchPolicy(currentPolicyPage-1);
    }
    else{
      if(file_driver =="s3"){
        await getNewPolicyFileUrl(acknowledgmentUserToken,index);
      }
      setActiveTab(activeTab - 1);
    }
    toggleLoading(false);
  };

  const getNewPolicyFileUrl = async (token,index) =>{
    toggleLoading(true);
    let httpRes = await axiosFetch.get(route('policy-management.campaigns.acknowledgement.new_policy_url'),{params:{token:token,policy_id:campaignAcknowledgments[index].policy_id}});
    campaignAcknowledgments[index].policy.path=httpRes.data.policy.path;
    setNewCampaignAcknowledgments(campaignAcknowledgments);
    toggleLoading(false);
  }

  const renderAsideLinks = () => {
    return newCampaignAcknowledgments.map((campaignAcknowledgment, index) => {
      return (
        <Nav.Item key={_.uniqueId()}>
          <Nav.Link
            eventKey={campaignAcknowledgment.id}
            className={`policy-list list-group-item list-group-item-action`}
            onClick={() => {
              handleTabNavClick(campaignAcknowledgment.id,index);
            }}
          >
            {decodeHTMLEntity(campaignAcknowledgment.policy.display_name)}
            {checkedPolicies.includes(
                    campaignAcknowledgment.token
                  )?<i className="fe-check float-end" style={{color: '#359f1d', fontSize: '18px'}} />:''}
          </Nav.Link>
        </Nav.Item>
      );
      // return (<a
      //     eventKey={campaignAcknowledgment.id}
      //     className={`policy-list list-group-item list-group-item-action ${(index == 0) ? 'active' : ''}`} data-toggle="list" href="#list-{{ $index }}"
      //     role="tab" aria-controls="home">
      //         { decodeHTMLEntity(campaignAcknowledgment.policy.display_name)}
      // </a>)
    });
  };

  /* For file policy review section rendered */
  const renderFilePreviewSection = (ext, policyPath, policy, index) => {
    if(!loading){
      if(policy.type === 'automated') {
        return(
            <div className="shadow" id="react-pdf-policy">
              <SizeMe>
                {({size}) => (
                    <Document file={route('documents.export', {id: policy.path, version: policy.version, token: campaignAcknowledgments[index].token, data_scope: policy.data_scope})} onLoadSuccess={onDocumentLoadSuccess}>
                      <Page pageNumber={pageNumber} width={size.width ? size.width : 1} />
                    </Document>
                )}
              </SizeMe>
              {numPages ?
                  <>
                    <p className="d-flex align-items-center justify-content-center">
                      Page {pageNumber} of {numPages}
                    </p>
                    <nav aria-label="pdf-pagination" className="d-flex align-items-center justify-content-center">
                      <ul className="pagination pagination-sm">
                        <li className={`page-item ${pageNumber === 1 ? 'disabled' : ''}`}><button className="page-link" onClick={() => setPageNumber(pageNumber - 1)}>Previous</button></li>
                        <li className={`page-item ${pageNumber === numPages ? 'disabled' : ''}`}><button className="page-link" onClick={() => setPageNumber(pageNumber + 1)}>Next</button></li>
                      </ul>
                    </nav>
                  </> : null
              }
            </div>
        )
      }
      return (
        <div>
          {/* if the  file type cant be displayed*/}
          {ext == "pdf" ? (
            // <object data={file_driver =="s3"?policyPath:asset(`/${policyPath}`)} width="100%" height={500}>
            //    <p>Your web browser doesn't have a PDF plugin.
            //     Instead you can <a href={file_driver =="s3"?policyPath:asset(`/${policyPath}`)}>click here to
            //     download the PDF file.</a></p>
            // </object>
            <div className="shadow" id="react-pdf-policy">
              {/* fixes done for oracle ksa fixes by base pdf */}
              {
                policy.base_pdf ? 
                  <Document 
                file={`data:application/pdf;base64,${policy.base_pdf}`}
                onLoadSuccess={onDocumentLoadSuccess}>
                  <Page pageNumber={pageNumber}/>
                </Document>
                :
                <Document file={file_driver =="s3"?policyPath:asset(`/${policyPath}`)} onLoadSuccess={onDocumentLoadSuccess}>
                  <Page pageNumber={pageNumber}/>
                </Document>
              } 
  
              {numPages &&
              <>
              <p className="d-flex align-items-center justify-content-center">
                Page {pageNumber} of {numPages}
              </p>
              <nav aria-label="pdf-pagination" className="d-flex align-items-center justify-content-center">
                <ul className="pagination pagination-sm">
                  <li className={`page-item ${pageNumber === 1 ? 'disabled' : ''}`}><span className="page-link cursor-pointer" onClick={() => setPageNumber(pageNumber - 1)}>Previous</span></li>
                  <li className={`page-item ${pageNumber === numPages ? 'disabled' : ''}`}><span className="page-link cursor-pointer" onClick={() => setPageNumber(pageNumber + 1)}>Next</span></li>
                </ul>
              </nav>
              </>
            }
            </div>
          ) : (
            <embed src={file_driver =="s3"?policyPath:asset(`/${policyPath}`)} width="100%" height={500}/>
            )}
        </div>
      );
    }
  };

  const handlePolicyChecked = (value) => {
    if(checkedPolicies.includes(value)){
      checkedPolicies.pop(value);
    }
    else{
      checkedPolicies.push(value);
    }
    setCheckedPolicies(checkedPolicies);
    // setCheckedPolicies(_.xor(checkedPolicies, [e.target.defaultValue]));
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
    // setIsFormSubmitting(true);
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
        onSuccess: (page) => {
          /* Starting loading button */
          // setIsFormSubmitting(false);
          enableSubmitButton()

        },
        onError: (errors) => {
          /* Starting loading button */
          // setIsFormSubmitting(false);
          enableSubmitButton()
        },
      }
    );
  };

  const renderTabContents = () => {
    return newCampaignAcknowledgments.map((campaignAcknowledgment, index) => {
      let isFirstLoop=false;
      if(paginationData.current_page == 1 ){
        isFirstLoop = index == 0;
      }
      let isLastLoop = false;
      if(paginationData.current_page == paginationData.last_page){
        isLastLoop = index == campaignAcknowledgments.length - 1;
      }
      let isLastElementInPagination = index == campaignAcknowledgments.length - 1 ;
      return (
        <Tab.Pane key={_.uniqueId()} eventKey={campaignAcknowledgment.id}>
          {!loading ?
            <div className="card-text">
              {campaignAcknowledgment.policy.type == "doculink" && (
                <>
                  <p>
                    This Policy is a doculink. Please follow the url below to see
                    the policy, and confirm that you acknowledge the policy after
                    viewing
                  </p>
                  <a href={campaignAcknowledgment.policy.path} target="_blank">
                    {campaignAcknowledgment.policy.path}
                  </a>
                </>
              )}
              {/*file preview section */}
              {campaignAcknowledgment.policy.type != "doculink" &&
                renderFilePreviewSection(
                  campaignAcknowledgment.policy.ext,
                  campaignAcknowledgment.policy.path,
                    campaignAcknowledgment.policy,
                    index
                )}              
              <div className="col-12 mt-3 text-center">
                <p>
                  I understand that if I have any questions, at any time, I will consult with my immediate supervisor or my Human Resource staff members.
                </p>
                <div className="form-check d-flex justify-content-center">
                  <input
                    type="checkbox"
                    name="agreed_policy[]"
                    // defaultValue={checkedPolicies.includes(
                    //     campaignAcknowledgment.token
                    //   )?1:0}
                    className="form-check-input me-1 cursor-pointer"
                    id={`checkmeout_${index}`}
                    onChange={(e) => {
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
                    I have read and understood the above policy.
                  </label>
                </div>

                  {errors.agreed_policy && (
                    <div className="invalid-feedback d-block">{errors.agreed_policy}</div>
                  )}
              </div>
              {/* next and prev button section */}
              <div className="row mt-5 " id="button_div">
                <div className="col-12 text-center clearfix">
                  {!isFirstLoop && (
                    <button
                      type="button"
                      className="ms-1 btn btn-primary btnPrevious"
                      onClick={() => {
                        handlePrevBtnClick(index);
                      }}
                    >
                      Previous
                    </button>
                  )}
                  {!isLastLoop && (
                    <button
                      type="button"
                      className="ms-1 btn btn-primary btnNext"
                      onClick={()=>{handleNextBtnClick(index,isLastElementInPagination)}}
                    >
                      Next
                    </button>
                  )}
                  {isLastLoop && (
                    <button className="ms-1 btn btn-primary custom-save-button"
                                        onClick={(e) => handleSubmit(e)}>
                                    Submit
                      <span className='custom-save-spinner'>
                        <img className='custom-spinner-image' style={{display: 'none'}} height="25px"></img>
                      </span>
                    </button>
                  )}
                </div>
              </div>
            </div>
            :
            <div className="card-text dummy-div"></div>
          }
        </Tab.Pane>
      );
    });
  };

  const toggleLoading = (value) =>{
      setLoading(value);
  }

  // TODO need to refactor sending checked_policies as param
  const fetchPolicy = (page) =>{
    localStorage.setItem('checked_policies', JSON.stringify(checkedPolicies));
    Inertia.get(route("policy-management.campaigns.acknowledgement.show",acknowledgmentUserToken),
        {
            page: page
        });
  }

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
            <strong>{decodeHTMLEntity(campaign.name)}</strong> policy management
            campaign. Please read the policy(ies) below and acknowledge the
            following policy(ies).
          </p>
        </div>
        <Tab.Container id="left-tabs-example" activeKey={activeTab} mountOnEnter>
          {/* aside links */}
          <div className="col-12 col-sm-4 text-center">
            <Nav variant="pills" className="flex-column">
              {renderAsideLinks()}
            </Nav>
            {paginationData.total > 10 &&
              <ReactPagination
                itemsCountPerPage={paginationData.per_page}
                totalItemsCount={paginationData.total}
                page={currentPolicyPage - 1}
                onChange={(page) => {
                  fetchPolicy(page);
                }}
              ></ReactPagination>
            }
          </div>
          <div className="col-12 col-sm-8">
            {/* <form
              // action="{{ route('policy-management.campaigns.acknowledgement.confirm') }}"
              // method="post"
              // onSubmit={handleSubmit}
            > */}
              <input
                type="hidden"
                name="campaign_acknowledgment_user_token"
                defaultValue={acknowledgmentUserToken}
              />
              <ContentLoader show={loading}>
                <Tab.Content>{renderTabContents()}</Tab.Content>
              </ContentLoader>
            {/* </form> */}
          </div>
        </Tab.Container>
      </div>
    </CampaignPolicyAcknowledgement>
  );
};

export default ShowPage;
