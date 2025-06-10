// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

contract VoteContract {
    address public admin;
    
    // Mapping to track if a voter has voted for a position in an election
    mapping(address => mapping(uint256 => mapping(string => bool))) public hasVoted;
    
    // Mapping to store vote counts for each candidate per position
    mapping(uint256 => mapping(string => uint256)) public voteCount;
    
    // Array to store all votes
    Vote[] public votes;

    // Struct to represent a vote with additional details
    struct Vote {
        uint256 electionId;
        address voter;
        uint256 positionId;
        string candidateId;
        uint256 timestamp;
        string candidateName; 
        string positionName;  
    }

    // Events for logging
    event VoteCast(uint256 electionId, address indexed voter, uint256 positionId, string candidateId, string candidateName, string positionName);

    // Modifiers
    modifier onlyAdmin() {
        require(msg.sender == admin, "Not authorized");
        _;
    }

    constructor() {
        admin = msg.sender;
    }

    // Function to cast a vote
    function castVote(
        uint256 electionId,
        uint256 positionId,
        string memory candidateId, 
        string memory candidateName,
        string memory positionName
    ) external {
        require(!hasVoted[msg.sender][electionId][candidateId], "Already voted for this position");

        hasVoted[msg.sender][electionId][candidateId] = true;
        voteCount[positionId][candidateId]++;
        votes.push(Vote(electionId, msg.sender, positionId, candidateId, block.timestamp, candidateName, positionName));
        emit VoteCast(electionId, msg.sender, positionId, candidateId, candidateName, positionName);
    }

    // Function to get the vote count for a candidate in a position
    function getVoteCount(uint256 positionId, string memory candidateId) external view returns (uint256) {
        return voteCount[positionId][candidateId];
    }

    // Function to retrieve all votes for a given election
    function getVotesByElection(uint256 electionId) external view returns (Vote[] memory) {
        uint256 count = 0;
        for (uint256 i = 0; i < votes.length; i++) {
            if (votes[i].electionId == electionId) {
                count++;
            }
        }

        Vote[] memory result = new Vote[](count);
        uint256 index = 0;
        for (uint256 i = 0; i < votes.length; i++) {
            if (votes[i].electionId == electionId) {
                result[index] = votes[i];
                index++;
            }
        }
        return result;
    }
}